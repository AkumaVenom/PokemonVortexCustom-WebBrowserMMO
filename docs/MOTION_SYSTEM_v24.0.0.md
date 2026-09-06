# v24.0.0 Motion System — 2026 Cinematic Motion & Battle Polish

## Design contract

v24.0.0 treats animation as a presentation layer, not a gameplay system. Motion communicates hierarchy, state changes, input acknowledgement and server-confirmed movement while the existing PHP/database authority remains definitive.

## Shared website motion

`assets/css/vortex-modern.css` and `assets/js/vortex-modern.js` provide the global motion vocabulary:

- short press/focus feedback for controls;
- restrained lift/highlight treatment for interactive cards and surfaces;
- progressive viewport reveal for major content surfaces;
- ambient shell motion that avoids blocking interaction;
- consistent focus-visible treatment alongside pointer hover states;
- a reusable `window.PVMotion` presentation helper for celebratory result feedback.

## Map actor motion

`assets/js/vortex-map.js` and `assets/js/world-map.js` now reconcile actor elements by stable `data-player-key` identities. A presence refresh updates CSS position variables on the existing element instead of rebuilding the entire actor layer.

This creates smooth interpolation while preserving these authority rules:

1. The browser never predicts a legal destination.
2. A movement command still posts to the existing server movement endpoint.
3. Actor coordinates rendered by the client come from server responses/presence data.
4. Vortex collision/transitions remain server-authored.
5. Kanto/Hoenn camera correction retains the established deterministic `behavior: 'instant'` contract; only actor motion interpolates.

Presentation states include facing direction, walking, spawn/leave, owner emphasis, bot hover/label reveal, encounter refresh and map transition feedback.

## Battle motion

The existing modern battle shell is retained so Wild, Standard/Trainer Snapshot and Live battle pages share a coherent presentation system rather than separate implementations. v24.0.0 adds:

- arena scan and ring motion;
- combatant entrance and subtle idle presentation;
- animated HP/team/progress fills;
- move/item/team control sheen and press acknowledgement;
- command-submission arena charge feedback;
- battle log/result/reward/roster reveal motion;
- deterministic decorative victory/capture/defeat particles.

The effects do not calculate damage, outcomes, captures, inventory, rewards, team state or Live PvP state.

## Accessibility and performance

`prefers-reduced-motion: reduce` disables/collapses nonessential transitions, keyframes, reveal displacement and celebration particles. Actor positions still update correctly without animated interpolation.

The map actor reconciler reuses nodes to reduce layout/DOM churn during presence polling. Animation is primarily transform/opacity based to stay on compositor-friendly properties where practical.

## Regression boundaries

The v24 release gate explicitly checks:

- schema remains revision 27;
- v23.7.1 bot population/behavior code remains intact;
- stable map actor identity and server-returned coordinates are used;
- Kanto/Hoenn deterministic camera contract is retained;
- global/battle/reduced-motion hooks are present;
- the modern battle wrapper still retains legacy form/state handling.
