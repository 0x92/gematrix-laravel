# Cipher Reference

This document lists all configured Gematrix ciphers with formulas, behavior notes, and full A-Z value tables.

## English Gematria (`english_gematria`)

- Description: A=6, B=12 ... Z=156. Each letter is six times its alphabet index.
- Formula: Value(letter) = Position(A1..Z26) * 6
- Method: Linear multiplier. The distance between letters stays constant.
- Example: C(18) + O(90) + M(78) + P(96) + U(126) + T(120) + E(30) + R(108) = 666

A-Z values:

```text
A=6, B=12, C=18, D=24, E=30, F=36, G=42, H=48, I=54, J=60, K=66, L=72, M=78, N=84, O=90, P=96, Q=102, R=108, S=114, T=120, U=126, V=132, W=138, X=144, Y=150, Z=156
```

## Simple (`simple_gematria`)

- Description: Classic alphabetical mapping: A=1 ... Z=26.
- Formula: Value(letter) = Position(A1..Z26)
- Method: Classic ordinal gematria without multipliers or offsets.
- Example: COMPUTER = 3+15+13+16+21+20+5+18 = 111

A-Z values:

```text
A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=10, K=11, L=12, M=13, N=14, O=15, P=16, Q=17, R=18, S=19, T=20, U=21, V=22, W=23, X=24, Y=25, Z=26
```

## Unknown (`unknown_gematria`)

- Description: Offset mapping: A=99 ... Z=124.
- Formula: Value(letter) = Position + 98
- Method: Constant offset on alphabet index. Relative distances are preserved while values shift upward.
- Example: A=99, B=100, ... Z=124

A-Z values:

```text
A=99, B=100, C=101, D=102, E=103, F=104, G=105, H=106, I=107, J=108, K=109, L=110, M=111, N=112, O=113, P=114, Q=115, R=116, S=117, T=118, U=119, V=120, W=121, X=122, Y=123, Z=124
```

## Pythagoras (`pythagoras_gematria`)

- Description: Specific mapping based on the reference file (including master numbers 11 and 22).
- Formula: Fixed mapping from the reference table
- Method: Non-linear; specific letters carry special values (including 11 and 22).
- Example: Values are read from a predefined table rather than derived linearly.

A-Z values:

```text
A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=1, K=11, L=3, M=4, N=5, O=6, P=7, Q=8, R=9, S=10, T=2, U=3, V=22, W=5, X=6, Y=7, Z=8
```

## Jewish (`jewish_gematria`)

- Description: Jewish mapping with expanded values (for example J=600, V=700, W=900).
- Formula: A..I = 1..9, then expanded jumps
- Method: From J onward, larger jumps are used (for example J=600, V=700, W=900).
- Example: J=600, T=100, U=200, V=700, W=900

A-Z values:

```text
A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=600, K=10, L=20, M=30, N=40, O=50, P=60, Q=70, R=80, S=90, T=100, U=200, V=700, W=900, X=300, Y=400, Z=500
```

## Prime (`prime_gematria`)

- Description: A=2, B=3, C=5 ... Z=101 (prime sequence).
- Formula: Value(letter) = n-th prime number
- Method: A=2, B=3, C=5 ... Z=101. Later letters spread apart faster than ordinal systems.
- Example: A=2, E=11, M=41, Z=101

A-Z values:

```text
A=2, B=3, C=5, D=7, E=11, F=13, G=17, H=19, I=23, J=29, K=31, L=37, M=41, N=43, O=47, P=53, Q=59, R=61, S=67, T=71, U=73, V=79, W=83, X=89, Y=97, Z=101
```

## Reverse Satanic (`reverse_satanic_gematria`)

- Description: Reverse-Satanic: A=61 ... Z=36.
- Formula: Value(letter) = 62 - position
- Method: Reverse logic with high baseline. A is high, Z is lower.
- Example: A=61, B=60, ... Z=36

A-Z values:

```text
A=61, B=60, C=59, D=58, E=57, F=56, G=55, H=54, I=53, J=52, K=51, L=50, M=49, N=48, O=47, P=46, Q=45, R=44, S=43, T=42, U=41, V=40, W=39, X=38, Y=37, Z=36
```

## Clock (`clock_gematria`)

- Description: 12-cycle mapping: A=1 ... L=12, M=1 ...
- Formula: Values wrap modulo 12
- Method: A..L = 1..12, then repeats at M.
- Example: A=1, L=12, M=1, N=2

A-Z values:

```text
A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=10, K=11, L=12, M=1, N=2, O=3, P=4, Q=5, R=6, S=7, T=8, U=9, V=10, W=11, X=12, Y=1, Z=2
```

## Reverse Clock (`reverse_clock_gematria`)

- Description: Reverse 12-cycle mapping based on the reference.
- Formula: Reversed clock mapping
- Method: 12-cycle in inverted order according to the reference.
- Example: A=2, B=1, C=12, ...

A-Z values:

```text
A=2, B=1, C=12, D=11, E=10, F=9, G=8, H=7, I=6, J=5, K=4, L=3, M=2, N=1, O=12, P=11, Q=10, R=9, S=8, T=7, U=6, V=5, W=4, X=3, Y=2, Z=1
```

## System 9 (`system9_gematria`)

- Description: A=9, B=18 ... Z=234 (9-system).
- Formula: Value(letter) = position * 9
- Method: Linear scaling by factor 9; same relations as Simple but amplified.
- Example: A=9, B=18, ... Z=234

A-Z values:

```text
A=9, B=18, C=27, D=36, E=45, F=54, G=63, H=72, I=81, J=90, K=99, L=108, M=117, N=126, O=135, P=144, Q=153, R=162, S=171, T=180, U=189, V=198, W=207, X=216, Y=225, Z=234
```

## Francis Bacon (`francis_bacon_gematria`)

- Description: Bacon mapping with +26 offset: A=27 ... Z=52.
- Formula: Value(letter) = Position(A1..Z26) + 26
- Method: Simple offset system commonly used as Francis Bacon cipher.
- Example: A=27, B=28, ... Z=52

A-Z values:

```text
A=27, B=28, C=29, D=30, E=31, F=32, G=33, H=34, I=35, J=36, K=37, L=38, M=39, N=40, O=41, P=42, Q=43, R=44, S=45, T=46, U=47, V=48, W=49, X=50, Y=51, Z=52
```

## Septenary (`septenary_gematria`)

- Description: Symmetric 1-7-1 wave across the alphabet.
- Formula: A=1..G=7, then mirrored down to M=1 and back up to T=7, ending Z=1
- Method: Non-linear mirrored sequence that emphasizes central symmetry in words.
- Example: A=1, G=7, M=1, N=1, T=7, Z=1

A-Z values:

```text
A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=6, I=5, J=4, K=3, L=2, M=1, N=1, O=2, P=3, Q=4, R=5, S=6, T=7, U=6, V=5, W=4, X=3, Y=2, Z=1
```

## Glyph Geometry (`glyph_geometry_gematria`)

- Description: Visual glyph complexity based on straight lines, curves, and enclosed areas.
- Formula: Value(letter) = 2x(lines) + 3x(curves) + 5x(enclosed_areas)
- Method: Approximates visual effort to draw uppercase glyphs, combining strokes and enclosed counters.
- Example: A=11 (3 lines + 1 enclosed area), O=8 (1 curve + 1 enclosed area)

A-Z values:

```text
A=11, B=18, C=3, D=10, E=8, F=6, G=5, H=6, I=2, J=5, K=6, L=4, M=8, N=6, O=8, P=10, Q=10, R=12, S=6, T=4, U=7, V=4, W=8, X=4, Y=6, Z=6
```

