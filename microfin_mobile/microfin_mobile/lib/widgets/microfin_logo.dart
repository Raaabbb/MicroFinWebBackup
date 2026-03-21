import 'package:flutter/material.dart';

class MicroFinLogo extends StatelessWidget {
  final double size;

  const MicroFinLogo({super.key, this.size = 100});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.28),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFF3B82F6), // Vibrant Blue
            Color(0xFF8B5CF6), // Royal Purple
          ],
        ),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF8B5CF6).withOpacity(0.4),
            blurRadius: size * 0.3,
            offset: Offset(0, size * 0.1),
          ),
          BoxShadow(
            color: Colors.white.withOpacity(0.15),
            offset: const Offset(1, 1),
            blurRadius: 1,
          ),
          BoxShadow(
            color: Colors.black.withOpacity(0.2),
            offset: const Offset(-1, -1),
            blurRadius: 1,
            spreadRadius: 1,
          ),
        ],
      ),
      child: CustomPaint(
        painter: _MLogoPainter(),
        size: Size(size, size),
      ),
    );
  }
}

class _MLogoPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill
      ..strokeCap = StrokeCap.round
      ..isAntiAlias = true;

    // We draw an abstract M that looks like dynamic rising bars
    final path = Path();
    
    final w = size.width;
    final h = size.height;
    
    // Abstract geometric M
    // Left bar
    path.moveTo(w * 0.25, h * 0.75);
    path.lineTo(w * 0.25, h * 0.40);
    path.lineTo(w * 0.40, h * 0.40);
    path.lineTo(w * 0.40, h * 0.75);
    path.close();

    // Middle/peak bar
    path.moveTo(w * 0.45, h * 0.75);
    path.lineTo(w * 0.45, h * 0.25);
    path.lineTo(w * 0.60, h * 0.25);
    path.lineTo(w * 0.60, h * 0.75);
    path.close();

    // Right bar
    path.moveTo(w * 0.65, h * 0.75);
    path.lineTo(w * 0.65, h * 0.50);
    path.lineTo(w * 0.80, h * 0.50);
    path.lineTo(w * 0.80, h * 0.75);
    path.close();

    // Give it a shadow glow before drawing the white vectors
    canvas.drawShadow(path, Colors.black, 4, true);
    
    // Draw the white bars
    canvas.drawPath(path, paint);
    
    // An overlapping geometric connection to form the M's middle v-shape
    final strokePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = w * 0.08
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.miter;
      
    final linePath = Path();
    linePath.moveTo(w * 0.32, h * 0.40);
    linePath.lineTo(w * 0.52, h * 0.55);
    linePath.lineTo(w * 0.72, h * 0.50);
    
    canvas.drawPath(linePath, strokePaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
