import 'package:flutter/widgets.dart';

/// Keeps visited tabs alive without building every dashboard on first paint.
///
/// A normal IndexedStack builds all of its children immediately. On web that
/// means hidden dashboards can start requests and expensive layout work before
/// the selected dashboard appears.
class LazyIndexedStack extends StatefulWidget {
  final int index;
  final List<Widget> children;

  const LazyIndexedStack({
    super.key,
    required this.index,
    required this.children,
  });

  @override
  State<LazyIndexedStack> createState() => _LazyIndexedStackState();
}

class _LazyIndexedStackState extends State<LazyIndexedStack> {
  final Set<int> _visited = <int>{};

  @override
  Widget build(BuildContext context) {
    _visited.add(widget.index);
    return Stack(
      fit: StackFit.expand,
      children: List.generate(widget.children.length, (index) {
        if (!_visited.contains(index)) return const SizedBox.shrink();
        return Offstage(
          offstage: index != widget.index,
          child: TickerMode(
            enabled: index == widget.index,
            child: widget.children[index],
          ),
        );
      }),
    );
  }
}
