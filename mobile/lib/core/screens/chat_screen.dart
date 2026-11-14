import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:mobile/core/utils/pht_datetime.dart';

import '../api/api_client.dart';
import '../providers/auth_provider.dart';
import 'login_screen.dart';
import '../../customer/screens/customer_shell.dart';
import '../../shared_widgets/driver_bottom_navigation.dart';

class ChatScreen extends StatefulWidget {
  final bool showBottomNavigation;
  const ChatScreen({super.key, this.showBottomNavigation = false});
  @override State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  List<dynamic> _messages = [];
  List<dynamic> _faqs = [];
  bool _loading = true, _sending = false;
  String? _error;
  Timer? _poll;

  bool get _isDriver => context.read<AuthProvider>().user?.role == 'driver';
  bool get _isGuest => !context.read<AuthProvider>().isAuthenticated;
  String get _chatPath => _isDriver ? '/driver/chat' : '/chat';

  @override void initState() { super.initState(); WidgetsBinding.instance.addPostFrameCallback((_) { _load(); _poll = Timer.periodic(const Duration(seconds: 12), (_) => _load(silent: true)); }); }
  @override void dispose() { _poll?.cancel(); _input.dispose(); _scroll.dispose(); super.dispose(); }

  Future<void> _load({bool silent = false}) async {
    try {
      final api = context.read<ApiClient>();
      // Guests can browse FAQs, but do not yet own a support conversation.
      // Calling the protected conversation route caused the chat dashboard to
      // fail with 401 before the FAQ data could render.
      if (_isGuest) {
        final faqs = await api.get('/faqs', auth: false);
        if (!mounted) return;
        setState(() {
          _messages = [];
          _faqs = faqs as List? ?? [];
          _loading = false;
          _error = null;
        });
        return;
      }
      final results = await Future.wait([api.get(_chatPath), api.get('/faqs', auth: false)]);
      if (!mounted) return;
      setState(() { _messages = ((results[0] as Map)['messages'] as List?) ?? []; _faqs = results[1] as List? ?? []; _loading = false; _error = null; });
      _toBottom();
    } catch (e) { if (mounted && !silent) setState(() { _loading = false; _error = e.toString(); }); }
  }
  void _toBottom() => WidgetsBinding.instance.addPostFrameCallback((_) { if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 180), curve: Curves.easeOut); });
  Future<void> _send() async {
    if (_isGuest) {
      Navigator.of(context, rootNavigator: true).push(MaterialPageRoute(builder: (_) => const LoginScreen()));
      return;
    }
    final text = _input.text.trim(); if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try { await context.read<ApiClient>().post('$_chatPath/messages', {'content': text}, auth: true); _input.clear(); await _load(silent: true); }
    catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()))); }
    finally { if (mounted) setState(() => _sending = false); }
  }
  void _faq(dynamic faq) { final q = faq['question']?.toString() ?? ''; final a = faq['answer']?.toString() ?? ''; final now = DateTime.now().toUtc().toIso8601String(); setState(() => _messages = [..._messages, {'id': 'faq-${DateTime.now().microsecondsSinceEpoch}', 'sender_role': _isDriver ? 'driver' : 'customer', 'content': q, 'created_at': now}, {'id': 'faq-answer-${DateTime.now().microsecondsSinceEpoch}', 'sender_role': 'admin', 'content': a, 'created_at': now}]); _toBottom(); }
  String _time(dynamic value) { try { final date = PhtDateTime.parse(value); final h = date.hour % 12 == 0 ? 12 : date.hour % 12; return '$h:${date.minute.toString().padLeft(2, '0')} ${date.hour >= 12 ? 'PM' : 'AM'}'; } catch (_) { return ''; } }

  void _selectDashboardTab(int index) {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => CustomerShell(initialIndex: index)),
    );
  }

  Widget _buildCustomerBottomNavigation() {
    const items = [
      (Icons.home_outlined, Icons.home_rounded, 'Home'),
      (Icons.article_outlined, Icons.article_rounded, 'Orders'),
      (Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, 'Payments'),
      (Icons.chat_bubble_outline_rounded, Icons.chat_bubble_rounded, 'Chat'),
      (Icons.person_outline_rounded, Icons.person_rounded, 'Profile'),
    ];

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE5F1FA))),
        boxShadow: [BoxShadow(color: Color(0x140878C9), blurRadius: 16, offset: Offset(0, -4))],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 62,
          child: Row(
            children: [
              for (var index = 0; index < items.length; index++)
                Expanded(
                  child: InkWell(
                    onTap: () => _selectDashboardTab(index),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(index == 3 ? items[index].$2 : items[index].$1,
                            color: index == 3 ? const Color(0xFF0878C9) : const Color(0xFF6F8BA3), size: 24),
                        const SizedBox(height: 3),
                        Text(items[index].$3,
                            style: TextStyle(fontSize: 11, fontWeight: index == 3 ? FontWeight.bold : FontWeight.w500,
                                color: index == 3 ? const Color(0xFF0878C9) : const Color(0xFF6F8BA3))),
                        const SizedBox(height: 3),
                        Container(width: 28, height: 2.5,
                            decoration: BoxDecoration(color: index == 3 ? const Color(0xFF0878C9) : Colors.transparent,
                                borderRadius: BorderRadius.circular(2))),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF4F9FD),
    extendBodyBehindAppBar: true,
    appBar: AppBar(
      title: const Text('AquaChat'),
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      foregroundColor: Colors.white,
      elevation: 0,
      systemOverlayStyle: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
    ),
    body: Container(
      decoration: BoxDecoration(
        image: DecorationImage(
          image: AssetImage(_isDriver
              ? 'assets/images/driver_dashboard_background.png'
              : 'assets/images/customer_dashboard_bg.png'),
          fit: BoxFit.cover,
        ),
      ),
      child: Padding(
        padding: EdgeInsets.only(top: MediaQuery.paddingOf(context).top + kToolbarHeight),
        child: _loading ? const Center(child: CircularProgressIndicator()) : Column(children: [
      Container(width: double.infinity, padding: const EdgeInsets.fromLTRB(16, 12, 16, 10), color: Colors.white.withValues(alpha: 0.92), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Text('Quick answers', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF064B87))), const SizedBox(height: 8), SizedBox(height: 38, child: ListView.separated(scrollDirection: Axis.horizontal, itemCount: _faqs.length, separatorBuilder: (_, _) => const SizedBox(width: 8), itemBuilder: (_, i) => ActionChip(label: Text(_faqs[i]['question']?.toString() ?? '', overflow: TextOverflow.ellipsis), onPressed: () => _faq(_faqs[i]))))])),
      if (_error != null) Padding(padding: const EdgeInsets.all(10), child: Text(_error!, style: const TextStyle(color: Colors.red))),
      Expanded(child: ListView.builder(controller: _scroll, padding: const EdgeInsets.all(16), itemCount: _messages.length, itemBuilder: (_, i) { final m = _messages[i] as Map; final mine = m['sender_role'] == (_isDriver ? 'driver' : 'customer'); return Align(alignment: mine ? Alignment.centerRight : Alignment.centerLeft, child: Container(constraints: const BoxConstraints(maxWidth: 300), margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10), decoration: BoxDecoration(color: mine ? const Color(0xFF0878C9) : Colors.white, borderRadius: BorderRadius.circular(14)), child: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [Text(m['content']?.toString() ?? '', style: TextStyle(color: mine ? Colors.white : const Color(0xFF183247))), const SizedBox(height: 3), Text(_time(m['created_at']), style: TextStyle(fontSize: 10, color: mine ? Colors.white70 : Colors.black45))]))); })),
      SafeArea(top: false, child: Padding(padding: const EdgeInsets.all(10), child: Row(children: [Expanded(child: TextField(controller: _input, minLines: 1, maxLines: 4, textInputAction: TextInputAction.newline, decoration: const InputDecoration(hintText: 'Message Admin…', filled: true, fillColor: Colors.white, border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(22)), borderSide: BorderSide.none)))), const SizedBox(width: 8), IconButton.filled(onPressed: _sending ? null : _send, icon: _sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Icon(Icons.send_rounded))]))),
    ]),
      ),
    ),
    bottomNavigationBar: widget.showBottomNavigation
        ? (_isDriver ? const DriverBottomNavigation(currentIndex: 3) : _buildCustomerBottomNavigation())
        : null,
  );
}
