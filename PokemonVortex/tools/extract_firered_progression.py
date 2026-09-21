#!/usr/bin/env python3
"""Extract and validate FireRed Rev 1 experience metadata; never copy ROM code/assets.

Usage: python3 extract_firered_progression.py /path/to/FireRed.gba --output-dir data
Only the original English BPRE revision 1 is accepted. No network or dependencies.
"""

import argparse
import hashlib
import json
from pathlib import Path
import struct
import sys


ROM_SHA256 = "729041b940afe031302d630fdbe57c0c145f3f7b6d9b8eca5e98678d0ca4d059"
ROM_SHA1 = "dd5945db9b930750cb39d00c84da8571feebf417"
EXPERIENCE_OFFSET = 0x253B54
SPECIES_OFFSET = 0x2547F4
NATIONAL_DEX_OFFSET = 0x25205E
SPECIES_NAMES_OFFSET = 0x245F50
SPECIES_STRIDE = 28
GROUP_NAMES = ("medium_fast", "erratic", "fluctuating", "medium_slow", "fast", "slow")
LEVEL_100_TOTALS = (1000000, 600000, 1640000, 1059860, 800000, 1250000)


def require(condition, message):
    if not condition:
        raise ValueError(message)


def reference_threshold(group, level):
    """Independent integer-math check; output data always comes from ROM bytes."""
    if level <= 1:
        return level
    cube = level ** 3
    if group == 0:
        return cube
    if group == 1:
        if level <= 50:
            return cube * (100 - level) // 50
        if level <= 68:
            return cube * (150 - level) // 100
        if level <= 98:
            return cube * ((1911 - 10 * level) // 3) // 500
        return cube * (160 - level) // 100
    if group == 2:
        if level <= 15:
            return cube * ((level + 1) // 3 + 24) // 50
        if level <= 36:
            return cube * (level + 14) // 50
        return cube * (level // 2 + 32) // 50
    if group == 3:
        return 6 * cube // 5 - 15 * level ** 2 + 100 * level - 140
    if group == 4:
        return 4 * cube // 5
    if group == 5:
        return 5 * cube // 4
    raise ValueError("Invalid growth group")


def decode_name(raw):
    charmap = {0x00: " ", 0xAB: "!", 0xAC: "?", 0xAD: ".", 0xAE: "-",
               0xB4: "'", 0xB5: "♂", 0xB6: "♀", 0xB8: ",", 0xBA: ":"}
    charmap.update({0xBB + i: chr(ord("A") + i) for i in range(26)})
    charmap.update({0xD5 + i: chr(ord("a") + i) for i in range(26)})
    charmap.update({0xA1 + i: str(i) for i in range(10)})
    result = []
    for byte in raw:
        if byte == 0xFF:
            break
        require(byte in charmap, "Unsupported name byte: 0x%02X" % byte)
        result.append(charmap[byte])
    return "".join(result).strip()


def extract(rom):
    require(len(rom) == 16 * 1024 * 1024, "Expected a 16 MiB original FireRed Rev 1 ROM")
    require(rom[0xAC:0xB0] == b"BPRE" and rom[0xBC] == 1,
            "Expected English FireRed BPRE revision 1")
    digest = hashlib.sha256(rom).hexdigest()
    require(digest == ROM_SHA256, "ROM SHA-256 mismatch; unsupported or modified ROM")
    require(hashlib.sha1(rom).hexdigest() == ROM_SHA1, "ROM SHA-1 mismatch")
    require((sum(rom[0xA0:0xBD]) + 0x19 + rom[0xBD]) & 0xFF == 0,
            "Invalid GBA header checksum")

    tables = {}
    for group, name in enumerate(GROUP_NAMES):
        values = list(struct.unpack_from("<101I", rom, EXPERIENCE_OFFSET + group * 404))
        require(values[-1] == LEVEL_100_TOTALS[group], "Unexpected level 100 total")
        require(all(a < b for a, b in zip(values, values[1:])), "Nonmonotonic EXP curve")
        for level, value in enumerate(values):
            require(value == reference_threshold(group, level),
                    "EXP mismatch: %s level %d" % (name, level))
        tables[name] = values

    species = {}
    for internal_id in range(1, 412):
        # These slots are obsolete Unown placeholders, not national species 387+.
        if 252 <= internal_id <= 276:
            continue
        national_id = struct.unpack_from("<H", rom, NATIONAL_DEX_OFFSET + (internal_id - 1) * 2)[0]
        require(1 <= national_id <= 386, "Invalid national dex mapping")
        require(national_id not in species, "Duplicate national dex mapping")
        offset = SPECIES_OFFSET + internal_id * SPECIES_STRIDE
        group = rom[offset + 0x13]
        base_exp = rom[offset + 0x09]
        require(group < 6 and base_exp > 0, "Invalid growth group/base EXP")
        name_offset = SPECIES_NAMES_OFFSET + internal_id * 11
        name = decode_name(rom[name_offset:name_offset + 11])
        require(bool(name), "Empty species name")
        species[national_id] = {
            "name": name,
            "internal_id": internal_id,
            "growth_group_id": group,
            "growth_group": GROUP_NAMES[group],
            "base_exp": base_exp,
        }
    require(set(species) == set(range(1, 387)), "Missing Gen 1-3 species")
    anchors = {1: ("BULBASAUR", 3, 64), 25: ("PIKACHU", 0, 82),
               113: ("CHANSEY", 4, 255), 252: ("TREECKO", 3, 65),
               290: ("NINCADA", 1, 65), 358: ("CHIMECHO", 4, 147),
               386: ("DEOXYS", 5, 215)}
    for national_id, expected in anchors.items():
        entry = species[national_id]
        require((entry["name"], entry["growth_group_id"], entry["base_exp"]) == expected,
                "Species anchor failed at dex %d" % national_id)

    slices = {
        "experience_tables": rom[EXPERIENCE_OFFSET:EXPERIENCE_OFFSET + 6 * 404],
        "species_records": rom[SPECIES_OFFSET:SPECIES_OFFSET + 412 * SPECIES_STRIDE],
        "national_dex_mapping": rom[NATIONAL_DEX_OFFSET:NATIONAL_DEX_OFFSET + 411 * 2],
        "species_names": rom[SPECIES_NAMES_OFFSET:SPECIES_NAMES_OFFSET + 412 * 11],
    }
    return {
        "schema_version": 1,
        "source": {
            "game": "Pokemon FireRed (English, Rev 1)",
            "game_code": "BPRE", "revision": 1,
            "rom_size_bytes": len(rom), "rom_sha1": ROM_SHA1, "rom_sha256": digest,
            "offsets": {"experience_tables": "0x253B54", "species_records": "0x2547F4",
                        "national_dex_mapping": "0x25205E", "species_names": "0x245F50"},
            "source_slice_sha256": {key: hashlib.sha256(value).hexdigest() for key, value in slices.items()},
        },
        "growth_groups": list(GROUP_NAMES),
        "experience_tables": tables,
        "species": {str(key): species[key] for key in sorted(species)},
    }


def php_value(value, indent=0):
    if isinstance(value, bool):
        return "true" if value else "false"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, str):
        return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"
    if isinstance(value, list):
        return "[" + ", ".join(php_value(item, indent) for item in value) + "]"
    if isinstance(value, dict):
        padding = "    " * (indent + 1)
        rows = [padding + php_value(key) + " => " + php_value(item, indent + 1)
                for key, item in value.items()]
        return "[\n" + ",\n".join(rows) + ",\n" + "    " * indent + "]"
    raise TypeError("Unsupported export value")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("rom", type=Path)
    parser.add_argument("--output-dir", type=Path, default=Path("."))
    args = parser.parse_args()
    try:
        data = extract(args.rom.read_bytes())
        args.output_dir.mkdir(parents=True, exist_ok=True)
        json_path = args.output_dir / "firered_progression.json"
        php_path = args.output_dir / "firered_progression.php"
        json_path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        php_path.write_text("<?php\n// Generated from validated FireRed Rev 1 ROM. Do not edit manually.\n"
                            "return " + php_value(data) + ";\n", encoding="utf-8")
    except (OSError, ValueError) as error:
        print("Extraction failed: " + str(error), file=sys.stderr)
        return 1
    print("Validated 606 ROM EXP thresholds and 386 species; wrote %s and %s" % (json_path, php_path))
    return 0


if __name__ == "__main__":
    sys.exit(main())
