# Kanto and Hoenn expansion encounters

The accepted Kanto and Hoenn encounter profiles remain unchanged. Added habitat profiles use species, level slots and selection weights from the original FireRed/LeafGreen and Emerald encounter tables. Both human exploration and autonomous trainers load the same combined regional catalogs and use the existing independent Vortex variety roll.

## Levels and varieties

- A new source slot whose maximum is **24 or lower** retains its original minimum and maximum, including fixed single levels.
- A new source slot whose maximum is **above 24** rolls **21–24**.
- Existing regional profiles and the shared runtime level guard retain their accepted behavior. No generated wild Pokémon can exceed level 24.
- Normal, Shiny, Dark, Mystic, Metallic and Shadow remain available through the existing shared variety roll: 311 Normal outcomes and five outcomes per special variety out of 336. Species selection remains independent of variety selection.
- These limits govern wild encounters. Captured Pokémon continue to earn EXP and level through the existing player/AI progression system.

## Habitat selection

New profiles preserve the original walking/cave and surf/underwater slot tables. FireRed and LeafGreen tables contribute equally so version-exclusive species remain obtainable. Where a location has both walking and water tables, the exploration profile combines both; this is the MMO's existing area-based encounter approach, rather than a recreation of the handheld game's separate movement modes. Individual source slots retain their original weights within each contributing table. Per-step encounter chances follow the established regional MMO range.

Fishing-only species, Rock Smash events, gifts, static legendaries and story encounters are not converted into ordinary roaming spawns. Buildings and event chambers with no ordinary random habitat have no wild profile. AI trainers can enter and traverse them, then continue catching and training when they reach a connected wild habitat.

Equivalent ocean-bottom sections reuse the original Emerald underwater habitat: Clamperl, Chinchou and Relicanth. The supplied underwater ship sections reuse that ship's original aquatic habitat. These section adaptations are marked in the source audit. Tide and cave sections can share the corresponding original habitat profile.

The source filenames label two Sevii forests incorrectly. The artwork and original layout dimensions identify the Six Island image as Pattern Bush and the Three Island image as Berry Forest; encounters follow the artwork. Original filenames remain recorded for provenance.

## Source and validation

The source facts come from the matching game decompilations:

- [pret/pokefirered — original FireRed/LeafGreen wild encounter tables](https://github.com/pret/pokefirered/blob/master/src/data/wild_encounters.json)
- [pret/pokeemerald — original Emerald wild encounter tables](https://github.com/pret/pokeemerald/blob/master/src/data/wild_encounters.json)

`docs/data/region_expansion_encounters.json` records each added profile's original source slots, source URL, downloaded-file SHA-256, level conversion evidence, every supplied filename's habitat decision, and hashes of all accepted baseline profiles. The runtime only loads the compact PHP catalogs; the audit JSON is not required on each movement request.

Run `php tests/region_expansion_encounters_gate.php` from the project root. The gate reconstructs every imported weighted entry from its original slots, checks the level rules, verifies all six exact Pokémon guide names or existing repair seeds and sprites, checks the deterministic variety distribution, and proves accepted profiles were not changed. It does not require a database server.
