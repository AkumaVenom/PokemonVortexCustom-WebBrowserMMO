#!/usr/bin/env python3
"""Build deterministic name-keyed EXP metadata from vendored source snapshots."""
import argparse
import csv
import hashlib
import json
import re
import unicodedata
from pathlib import Path

GROUPS = ['medium_fast', 'erratic', 'fluctuating', 'medium_slow', 'fast', 'slow']
RATE_GROUP = {1: 'slow', 2: 'medium_fast', 3: 'fast', 4: 'medium_slow', 5: 'erratic', 6: 'fluctuating'}
PREFIX = re.compile(r'^(?:(?:Shiny|Dark|Metallic|Mystic|Shadow|Ancient)\s+)+', re.I)

def key(name):
    name = PREFIX.sub('', name.strip()).replace('♀', 'f').replace('♂', 'm')
    name = unicodedata.normalize('NFKD', name).encode('ascii', 'ignore').decode()
    return re.sub('[^a-z0-9]', '', name.lower())

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source-dir', type=Path, default=Path(__file__).parent / 'experience_sources')
    parser.add_argument('--baseline', type=Path, required=True)
    parser.add_argument('--rom-json', type=Path, required=True)
    parser.add_argument('--output', type=Path, default=Path(__file__).resolve().parents[1] / 'data/experience_species.json')
    args = parser.parse_args()
    source = args.source_dir
    species_rows = list(csv.DictReader((source / 'pokemon_species.csv').open()))
    pokemon_rows = list(csv.DictReader((source / 'pokemon.csv').open()))
    assert len(species_rows) == 898 and len(pokemon_rows) == 1351
    by_dex = {int(row['id']): row for row in species_rows}
    default_pokemon = {int(row['species_id']): row for row in pokemon_rows if row['is_default'] == '1'}
    rom = json.loads(args.rom_json.read_text())
    # Cross-check the ID conversion against all original 386 species, including all six groups.
    for dex, row in rom['species'].items():
        assert RATE_GROUP[int(by_dex[int(dex)]['growth_rate_id'])] == row['growth_group'], dex

    species = {}
    for dex, row in by_dex.items():
        pokemon = default_pokemon[dex]
        group = RATE_GROUP[int(row['growth_rate_id'])]
        species[row['identifier']] = {'growth_rate': GROUPS.index(group), 'growth_group': group,
            'base_exp': int(pokemon['base_experience']), 'national_dex': dex,
            'source': 'veekun-growth+pokeapi-yield'}
    for pokemon in pokemon_rows:
        dex = int(pokemon['species_id'])
        if dex not in by_dex or not pokemon['base_experience']:
            continue
        target = by_dex[dex]['identifier']
        if pokemon['identifier'] != target:
            species[pokemon['identifier']] = {**species[target], 'base_exp': int(pokemon['base_experience']),
                'base_species': target}
    # The baseline has custom creatures without an official game growth assignment.
    # Their policy is deliberate and explicit, never an unknown-name fallback.
    for custom in ['darkrown', 'dratinice', 'dratinilic', 'dratinire', 'insekuta', 'missingno']:
        species[custom] = {'growth_rate': 0, 'growth_group': 'medium_fast', 'base_exp': 64,
            'national_dex': None, 'source': 'vortex-custom-policy-v1'}

    aliases = {key(name): name for name in species}
    aliases.update({'nidoranf': 'nidoran-f', 'nidoranm': 'nidoran-m', 'mrmime': 'mr-mime',
        'mimejr': 'mime-jr', 'flabebe': 'flabebe', 'farfetchd': 'farfetchd',
        'flaffy': 'flaaffy', 'gitatina': 'giratina', 'manaphyegg': 'manaphy'})
    form_aliases = {
        'Basculin (Blue Stripe)': 'basculin-blue-striped', 'Basculin (Red Stripe)': 'basculin-red-striped',
        'Castform (Fire)': 'castform-sunny', 'Castform (Ice)': 'castform-snowy', 'Castform (Water)': 'castform-rainy',
        'Darmanitan (Zen Mode)': 'darmanitan-zen', 'Keldeo (Resolution)': 'keldeo-resolute',
        'Meowstic (F)': 'meowstic-female', 'Meowstic (M)': 'meowstic-male',
        'Wormadam (Plant)': 'wormadam-plant', 'Wormadam (Sand)': 'wormadam-sandy', 'Wormadam (Steel)': 'wormadam-trash',
        'Rotom (Cut)': 'rotom-mow', 'Rotom (Spin)': 'rotom-fan', 'Gourgeist (Super2)': 'gourgeist-super',
    }
    for name, target in form_aliases.items():
        assert target in species, target
        aliases[key(name)] = target

    inherited = {}
    skipped = []
    images = args.baseline / 'PokemonVortex/html/static/images/pokemon'
    for image in sorted(images.iterdir()):
        if not image.is_file() or image.suffix.lower() != '.gif':
            continue
        name = PREFIX.sub('', image.stem)
        if key(name) in aliases:
            continue
        base = re.sub(r'^(?:Pink|Clone|Crystal)\s+', '', name)
        if key(base) not in aliases:
            base = re.sub(r'\s*\([^)]*\)\s*', '', base)
        if key(base) in aliases:
            aliases[key(name)] = aliases[key(base)]
            inherited[name] = aliases[key(base)]
        else:
            skipped.append(name)

    sql = (args.baseline / 'database/pokemon_vortex_full.sql').read_text()
    guide = '\n'.join(line for line in sql.splitlines() if line.startswith('INSERT INTO `pguide`'))
    catalog = [name for _, name in re.findall(r"\((\d+),'((?:\\.|[^'\\])*)'", guide)]
    assert len(catalog) == 4728
    missing = [name for name in catalog if key(name) not in aliases]
    assert not missing, missing
    source_files = {}
    for name, url in [('pokemon_species.csv', 'https://raw.githubusercontent.com/veekun/pokedex/master/pokedex/data/csv/pokemon_species.csv'),
                      ('pokemon.csv', 'https://raw.githubusercontent.com/PokeAPI/pokeapi/master/data/v2/csv/pokemon.csv')]:
        source_files[name] = {'url': url, 'sha256': hashlib.sha256((source / name).read_bytes()).hexdigest(),
            'retrieved_date': '2026-09-21', 'git_commit': None}
    result = {'schema_version': 1, 'metadata': {'source_files': source_files,
        'pinning': 'Exact UTF-8 LF source snapshots are SHA-256 pinned; upstream commit IDs were unavailable through the supported retrieval interface.',
        'authority': 'FireRed ROM overrides canonical National Dex 1-386 values. Supplemental forms inherit the same species growth group; yields identify the source snapshot.',
        'custom_policy': {'names': ['Darkrown', 'Dratinice', 'Dratinilic', 'Dratinire', 'Insekuta', 'Missingno.'],
            'growth_group': 'medium_fast', 'base_exp': 64, 'reason': 'Project-specific species absent from official game data; explicit stable balance policy.'},
        'inherited_form_policy': 'Cosmetic, event, and asset-only custom forms without distinct canonical yield records inherit their base species values. These aliases are project conventions, not claims of official form data.',
        'inherited_form_aliases': inherited, 'ignored_non_species_assets': sorted(set(skipped)),
        'validation': {'rom_growth_groups_matched': 386, 'catalog_rows_mapped': len(catalog), 'catalog_base_names_mapped': len({PREFIX.sub('', n) for n in catalog})}},
        'species': species, 'aliases': dict(sorted(aliases.items()))}
    args.output.write_text(json.dumps(result, ensure_ascii=False, separators=(',', ':')) + '\n')
    print(json.dumps({'species_and_forms': len(species), 'aliases': len(aliases), 'mapped_catalog_rows': len(catalog),
                      'inherited_forms': len(inherited), 'ignored_assets': sorted(set(skipped))}, indent=2))

if __name__ == '__main__':
    main()
