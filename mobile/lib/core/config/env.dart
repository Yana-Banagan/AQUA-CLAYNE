class Env {
  // Override per target without editing source, e.g.
  // flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.1.4:8000/api/v1',
  );

  static const String supabaseUrl = 'https://evnhwmmwsnxizcoejqhz.supabase.co';

  static const String supabaseAnonKey =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2bmh3bW13c254aXpjb2VqcWh6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODczMjQxNjYsImV4cCI6MjEwMjkwMDE2Nn0.-IDDlk5-cEvzKuyWC0JAQ5oHtUS5_ue0F0Hcmycwk7k';
}
