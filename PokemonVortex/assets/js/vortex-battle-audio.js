/* Pokemon Vortex NXT v32 — server-resolved battle audio timelines.
 * Load after vortex-modern.js and the PVAudio manager. No gameplay requests,
 * command prediction, animation changes, or motion-preference dependencies.
 */
(() => {
  'use strict';
  const doc = document;
  const boundedText = value => (typeof value === 'string' || typeof value === 'number') ? String(value).slice(0, 240) : '';
  const accepted = value => value === true || value === 1 || value === '1';
  const safeJson = value => {
    try { const result = JSON.parse(value || '{}'); return result && typeof result === 'object' && !Array.isArray(result) ? result : {}; }
    catch (_) { return {}; }
  };
  const outcome = value => {
    const key = boundedText(value).toLowerCase();
    if (key === 'won' || key === 'win') return 'won';
    if (key === 'lost' || key === 'loss') return 'lost';
    return key === 'captured' ? 'captured' : '';
  };
  const strikeDuration = data => data?.critical ? .755 : .685;
  const now = () => window.performance?.now?.() ?? Date.now();

  const begin = () => {
    const audio = window.PVAudio;
    const battle = window.PV_AUDIO_CONTEXT?.battle;
    if (!audio || !battle || typeof battle !== 'object' || typeof audio.claim !== 'function') return;
    const epoch = now();
    const scheduled = [];
    const id = boundedText(battle.id);
    const mode = boundedText(battle.mode).toLowerCase();
    const resultKind = outcome(battle.status);
    const claimed = new Set();
    const claim = key => {
      if (!key || claimed.has(key)) return false;
      claimed.add(key);
      try { return audio.claim(key) === true; } catch (_) { return false; }
    };
    const cue = (key, time) => scheduled.push({method:'cue', value:key, time});
    const strike = (data, time) => {
      if (!data || typeof data !== 'object' || !boundedText(data.move)) return time;
      scheduled.push({method:'strike', value:data, time});
      return time + strikeDuration(data);
    };
    const result = (engine, time) => {
      if (!id || !resultKind || !claim(`battle:${engine}:${id}:result:${resultKind}`)) return;
      scheduled.push({method:'result', value:resultKind, time});
    };
    const faint = (flag, time) => {
      if (!flag) return time;
      cue('faint', time);
      return time + .660;
    };

    const wildNode = doc.getElementById('pv-wild-battle-fx');
    const liveSurface = doc.querySelector('[data-pv-live-runtime][data-pv-live-turn]');
    const standardSurface = doc.querySelector('[data-pv-standard-battle-surface]');

    if (wildNode) {
      const fx = safeJson(wildNode.textContent);
      const eventId = boundedText(fx.id);
      let time = .330;
      let captureResultTime = null;
      if (id && eventId && claim(`battle:wild:${id}:event:${eventId}`)) {
        switch (boundedText(fx.action)) {
          case 'attack':
            time = strike(fx.player, time);
            time = faint(fx.enemy_fainted, time);
            time = strike(fx.enemy, time);
            time = faint(fx.player_fainted, time);
            break;
          case 'heal':
            if (fx.heal && Number(fx.heal.amount) > 0) { cue('heal', time); time += .650; }
            time = strike(fx.enemy, time);
            time = faint(fx.player_fainted, time);
            break;
          case 'switch':
            if (fx.switch) { cue('sendout', time); time += .700; }
            time = strike(fx.enemy, time);
            time = faint(fx.player_fainted, time);
            break;
          case 'ball': {
            // Only the persisted capture object certifies a consumed ball and
            // committed catch outcome. Never infer success from a form click.
            if (!fx.capture || typeof fx.capture.caught !== 'boolean') break;
            const caught = fx.capture.caught;
            cue('throw', time);
            time += .650;
            cue('absorb', time);
            time += .110;
            if (doc.querySelector('[data-pv-battle-fx-stage] [data-pv-fighter="enemy"] img')) time += .430;
            time += .340;
            cue('bounce', time);
            time += .140;
            for (let i = 0; i < (caught ? 3 : 2); i += 1) { cue('shake', time); time += .375; }
            cue(caught ? 'caught' : 'breakout', time);
            if (caught) { captureResultTime = time; time += 1.050; }
            else {
              time += .720;
              time = strike(fx.enemy, time);
              time = faint(fx.player_fainted, time);
            }
            break;
          }
          case 'run':
            cue('run', time); time += .620;
            break;
          case 'intro':
            cue('sendout', time); time += .720;
            break;
          default: break;
        }
        if (fx.player_fainted && fx.auto_switch) { cue('sendout', time); time += .700; }
      }
      result('wild', captureResultTime ?? time);
    } else if (liveSurface) {
      const match = id || boundedText(liveSurface.dataset.pvLiveMatchId);
      const turn = Math.max(0, Math.trunc(Number(liveSurface.dataset.pvLiveTurn) || 0));
      const phase = boundedText(liveSurface.dataset.pvLivePhase);
      const moveTypes = safeJson(doc.getElementById('pv-live-battle-move-types')?.textContent);
      const entries = Array.from(liveSurface.querySelectorAll('.pv-wild-log > div'))
        .map(row => ({turn: Number(row.dataset.pvLogTurn), text: (row.querySelector('span')?.textContent || '').trim()}))
        .filter(entry => entry.turn === turn && entry.text)
        .reverse();
      const resolved = entries.filter(entry => !/^(.+?) selected (.+?)\.$/i.test(entry.text));
      let time = .360;
      if (match && turn > 0 && ['command','switch','complete'].includes(phase)
          && resolved.some(entry => / used | switched to | fainted\.$/.test(entry.text))
          && claim(`battle:live:${match}:turn:${turn}`)) {
        // Use server log order: switch, item, initiative-ordered attacks, KOs.
        // Identical-species mirrors need no guessed actor identity for audio.
        for (const entry of resolved) {
          const text = entry.text;
          if (/^.+? used .+? and (?:restored \d+ HP|cleared its status)\.$/i.test(text)) {
            cue('heal', time); time += .650; continue;
          }
          if (/^.+? switched to .+?\.$/i.test(text)) {
            cue('sendout', time); time += .700; continue;
          }
          if (/^.+? fainted\.$/i.test(text)) {
            time = faint(true, time); continue;
          }
          const attack = text.match(/^(.+?) used (.+?)(?: and dealt (\d+) HP damage(?: \(([^)]+)\))?|, but the attack missed)\.?$/i);
          if (!attack) continue;
          const move = attack[2].trim();
          const label = String(attack[4] || '').toLowerCase();
          const effectiveness = /ultra/.test(label) ? 4 : /super/.test(label) ? 2 : /not very|resist/.test(label) ? .5 : /no effect|immune/.test(label) ? 0 : 1;
          time = strike({move, type:moveTypes[move] || 'normal', hit:!/, but the attack missed\.?$/i.test(text), damage:Number(attack[3] || 0), effectiveness}, time);
        }
      }
      // Selecting a starter or a replacement does not increment runtime.turn.
      // Separate selection keys keep later poll revisions from replaying a turn
      // while still allowing each newly accepted send-out to be heard once.
      for (const entry of entries) {
        const selection = entry.text.match(/^(.+?) selected (.+?)\.$/i);
        if (match && selection && claim(`battle:live:${match}:selection:${turn}:${selection[1]}:${selection[2]}`)) {
          cue('sendout', time); time += .700;
        }
      }
      result('live', time);
    } else if (standardSurface || mode === 'standard') {
      const fx = safeJson(standardSurface?.dataset.pvStandardFx);
      const eventId = boundedText(battle.eventId);
      let time = .330;
      if (id && eventId && claim(`battle:standard:${id}:event:${eventId}`)) {
        const action = boundedText(fx.action || doc.body?.dataset.pvBattleAction);
        const stage = standardSurface?.querySelector('.pv-wild-arena');
        const player = stage?.querySelector('[data-pv-fighter="player"]');
        const enemy = stage?.querySelector('[data-pv-fighter="enemy"]');
        const isFainted = fighter => fighter && fighter.dataset.pvFighterHp !== undefined && Number(fighter.dataset.pvFighterHp) <= 0;
        if (action === 'attack') {
          time = strike(fx.enemy, time);
          time = strike(fx.player, time);
          time = faint(isFainted(player), time);
          time = faint(isFainted(enemy), time);
        } else if (action === 'item') {
          // The legacy body action precedes stock/effect validation. Its value
          // alone cannot authorize a heal cue for a rejected or ineffective item.
          if (accepted(battle.itemAccepted)) cue('heal', time);
          time += .650;
          time = strike(fx.enemy, time);
          time = faint(isFainted(player), time);
        } else if (accepted(battle.switchAccepted)) {
          cue('sendout', time); time += .620;
        }
      }
      result('standard', time);
    } else if (['wild','live'].includes(mode)) {
      // The durable result receipt shares the battle's result claim key.
      result(mode, .330);
    }

    if (!scheduled.length) return;
    // Audio permission is not a game-event queue: claims happen above even if
    // muted/locked. If startup is slow, consume and drop this old timeline.
    Promise.resolve(audio.ready).then(() => {
      if (doc.visibilityState === 'hidden' || now() - epoch > 1500) {
        if(resultKind) audio.restoreResult?.(resultKind);
        return;
      }
      const elapsed = Math.max(0, (now() - epoch) / 1000);
      const keys = Array.from(new Set(scheduled.filter(event => event.method === 'cue').map(event => event.value)));
      try { Promise.resolve(audio.prepare?.(keys)).catch(() => {}); } catch (_) { /* Optional cache warmup never blocks gameplay. */ }
      for (const event of scheduled) {
        // Drop beats already missed during initialization; never burst old SFX.
        if (event.time < elapsed - .100) {
          if(event.method==='result') audio.restoreResult?.(event.value);
          continue;
        }
        try { audio[event.method]?.(event.value, Math.max(0, event.time - elapsed)); }
        catch (_) { /* Sound failure cannot interrupt battle controls. */ }
      }
    }).catch(() => {});
  };

  // vortex-modern.js upgrades trainer DOM synchronously at its earlier script
  // position. The microtask also lets its registered DOM-ready handlers finish.
  const start = () => Promise.resolve().then(begin).catch(() => {});
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', start, {once:true});
  else start();
})();
