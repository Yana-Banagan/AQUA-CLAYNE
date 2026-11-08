import 'dart:convert';

import 'package:path/path.dart' as path;
import 'package:sqflite/sqflite.dart';

/// Local SQLite read cache and durable outbox. Credentials and payment data
/// are never written here; outbox records are scoped to the authenticated user.
class OfflineCache {
  static const _databaseName = 'aqua_clayne_cache.db';
  static const _table = 'api_cache';
  Database? _database;

  Future<Database> get _db async {
    if (_database != null) return _database!;
    final directory = await getDatabasesPath();
    _database = await openDatabase(
      path.join(directory, _databaseName),
      version: 2,
      onCreate: (db, _) async {
        await db.execute('''
        CREATE TABLE $_table (
          cache_key TEXT PRIMARY KEY,
          payload TEXT NOT NULL,
          expires_at INTEGER NOT NULL,
          updated_at INTEGER NOT NULL
        )
      ''');
        await _createOutbox(db);
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) await _createOutbox(db);
      },
    );
    return _database!;
  }

  Future<void> _createOutbox(DatabaseExecutor db) => db.execute('''
    CREATE TABLE IF NOT EXISTS api_outbox (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      owner_id TEXT NOT NULL,
      method TEXT NOT NULL,
      path TEXT NOT NULL,
      payload TEXT NOT NULL,
      coalesce_key TEXT,
      created_at INTEGER NOT NULL,
      attempts INTEGER NOT NULL DEFAULT 0,
      last_error TEXT,
      UNIQUE(owner_id, coalesce_key)
    )
  ''');

  Future<void> write(String key, dynamic value, {required Duration ttl}) async {
    final now = DateTime.now().millisecondsSinceEpoch;
    final db = await _db;
    await db.insert(
      _table,
      {
        'cache_key': key,
        'payload': jsonEncode(value),
        'expires_at': now + ttl.inMilliseconds,
        'updated_at': now,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    await db.delete(
      _table,
      where: 'expires_at < ?',
      whereArgs: [now - const Duration(days: 7).inMilliseconds],
    );
  }

  /// Expired data is only returned after an API failure, never in preference
  /// to a successful cloud response.
  Future<dynamic> read(String key, {bool allowExpired = false}) async {
    final db = await _db;
    final rows = await db.query(_table, where: 'cache_key = ?', whereArgs: [key], limit: 1);
    if (rows.isEmpty) return null;
    final row = rows.first;
    if (!allowExpired && DateTime.now().millisecondsSinceEpoch > (row['expires_at'] as int)) return null;
    try {
      return jsonDecode(row['payload'] as String);
    } catch (_) {
      await db.delete(_table, where: 'cache_key = ?', whereArgs: [key]);
      return null;
    }
  }

  Future<void> enqueueMutation({
    required String ownerId,
    required String method,
    required String path,
    required Map<String, dynamic> payload,
    String? coalesceKey,
    bool replacePayload = false,
  }) async {
    final db = await _db;
    final now = DateTime.now().millisecondsSinceEpoch;
    await db.transaction((txn) async {
      if (coalesceKey != null) {
        final rows = await txn.query(
          'api_outbox',
          where: 'owner_id = ? AND coalesce_key = ?',
          whereArgs: [ownerId, coalesceKey],
          limit: 1,
        );
        if (rows.isNotEmpty) {
          final id = rows.first['id'];
          final oldPayload = jsonDecode(rows.first['payload'] as String);
          final merged = replacePayload
              ? payload
              : <String, dynamic>{
                  if (oldPayload is Map<String, dynamic>) ...oldPayload,
                  ...payload,
                };
          await txn.update(
            'api_outbox',
            {'payload': jsonEncode(merged), 'created_at': now, 'attempts': 0, 'last_error': null},
            where: 'id = ?',
            whereArgs: [id],
          );
          return;
        }
      }
      await txn.insert('api_outbox', {
        'owner_id': ownerId,
        'method': method,
        'path': path,
        'payload': jsonEncode(payload),
        'coalesce_key': coalesceKey,
        'created_at': now,
      });
    });
  }

  Future<List<Map<String, dynamic>>> pendingMutations(String ownerId) async {
    final rows = await (await _db).query(
      'api_outbox',
      where: 'owner_id = ?',
      whereArgs: [ownerId],
      orderBy: 'id ASC',
    );
    return rows.map((row) {
      final mutation = Map<String, dynamic>.from(row);
      mutation['payload'] = jsonDecode(row['payload'] as String) as Map<String, dynamic>;
      return mutation;
    }).toList();
  }

  Future<int> pendingMutationCount(String ownerId) async =>
      Sqflite.firstIntValue(await (await _db).rawQuery(
        'SELECT COUNT(*) FROM api_outbox WHERE owner_id = ?', [ownerId],
      )) ?? 0;

  Future<void> removeMutation(int id) async {
    await (await _db).delete('api_outbox', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> recordMutationFailure(int id, String message) async {
    await (await _db).rawUpdate(
      'UPDATE api_outbox SET attempts = attempts + 1, last_error = ? WHERE id = ?',
      [message, id],
    );
  }

  Future<void> clear() async {
    final db = await _db;
    await db.delete(_table);
  }

  Future<void> dispose() async {
    await _database?.close();
    _database = null;
  }
}
