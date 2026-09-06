# Kanto Resized Presentation — v21.5

v21.5 builds directly on the accepted v21.4 Kanto runtime.

## Presentation contract

- The individual FireRed/LeafGreen maps from v21.4 remain the authoritative logical world reference.
- The user-supplied `KantoResized.zip` PNGs are the fixed gameplay presentation images.
- Each resized image is exactly 2× the dimensions of its corresponding accepted v21.4 area image.
- The browser displays each resized PNG at its own natural dimensions. It is not fitted, shrunk, enlarged, or transformed by a Kanto zoom control.
- The existing Kanto map panel remains a normal scrollable viewport. Large maps extend beyond it and are explored using the same scrolling behavior already present in v21.4.

## Coordinates

The server continues to store the accepted v21.4 logical coordinates. A logical FireRed cell remains 16px in the source model, while the resized presentation maps use 32px for that same cell. Collision, encounters, transitions, saved positions and multiplayer presence therefore do not change.

Trainer sprites are enlarged only at the presentation layer so their feet remain aligned with the resized 32px display cells. The server still receives and broadcasts only logical X/Y positions.

## No selectable scaling

There are no Kanto 1×/2×/3×/4× controls and no alternate Kanto zoom modes. v21.5 has one fixed presentation size: the supplied resized PNG itself.

## v22.8.1 cross-browser compositor compatibility

The v21.5 Kanto presentation contract remains authoritative: the supplied resized PNGs are still preserved byte-for-byte, the map stage remains at the source image's exact natural dimensions, the logical movement grid remains 16px and the fixed presentation grid remains 32px. No zoom, fit-to-panel scaling or coordinate conversion has been introduced.

Real Microsoft Edge/Chromium testing later exposed a rendering-only failure on sufficiently large single bitmap surfaces. v22.8.1 therefore presents Kanto source images larger than 2048px on either axis using exact, lossless 1024px crops placed at their original integer coordinates. The crops are derived presentation assets only; they do not replace the authoritative source PNGs. Recomposition auditing confirms pixel-for-pixel equality with every tiled source map.

The native Kanto stage also avoids whole-stage CSS filtering during movement, preventing Chromium from requesting an oversized off-screen compositor surface. Firefox uses the same tiled path for the affected maps, while smaller maps remain on the accepted single-image path. The v22.3.1 safe-centering and camera-origin contract is preserved.
