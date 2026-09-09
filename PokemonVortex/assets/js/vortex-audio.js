/* v32.0.3 — public audio access diagnostics and recoverable manifest/track loading. All audio is presentation-only and local to this origin. */
(() => {
  'use strict';
  const node = document.getElementById('pv-audio-config');
  if (!node || window.PVAudio) return;
  let config;
  try { config = JSON.parse(node.textContent); } catch (_) { return; }
  window.PV_AUDIO_CONTEXT = config.context;
  const $ = id => document.getElementById(id);
  const clamp = (n, fallback = 0) => Number.isFinite(Number(n)) ? Math.max(0, Math.min(100, Number(n))) : fallback;
  const safeRead = (storage, key, fallback) => { try { return JSON.parse(window[storage].getItem(key)) ?? fallback; } catch (_) { return fallback; } };
  const safeWrite = (storage, key, value) => { try { window[storage].setItem(key, JSON.stringify(value)); } catch (_) {return false;} return true; };
  const scope = `pv-audio-v32:${config.base}:${config.account || 'guest'}`;
  const clean = (p={}) => ({enabled:![false,0,'0'].includes(p?.enabled), music:clamp(p?.music??35,35), effects:clamp(p?.effects??70,70), revision:Math.max(0,Number(p?.revision)||0)});
  let prefs = clean(config.preferences), saved = {...prefs};
  const cached=safeRead('localStorage',scope,null);
  if(cached && (!config.account || Number(cached.revision)>saved.revision)) prefs=saved=clean(cached);
  let ctx, master, musicBus, effectsBus, musicVoice, musicKey = '', desired = config.context.track;
  let manifest = {tracks:{},aliases:{},moves:{},types:{}}, loadEpoch = 0, cueEpoch = 0;
  let manifestLoaded=false, manifestPending=null, manifestFailure=null, playbackMessage='';
  const failedTracks=new Set();
  let unlocked = false, gone = false, failed = false;
  let saving = false, dirty = false, editCount = 0, conflicts = 0, retries = 0, saveTimer, musicLoading = '';
  let activationAttempt=false;
  const buffers = new Map(), pending = new Map(), voices = new Set(), timers = new Map();
  const touched = new Map();
  const claims = new Set(safeRead('sessionStorage', scope+':events', []));
  const instance = `${Date.now()}:${Math.random()}`;
  let channel;
  try { channel = new BroadcastChannel(scope); } catch (_) {}
  const broadcast = message => { try { channel?.postMessage({...message, instance}); } catch (_) {} };
  // Focus can remain in browser chrome after navigation. Only a hidden document
  // or an actually suspended audio context should silence an enabled game page.
  const usable = () => prefs.enabled && !document.hidden && !gone && unlocked && ctx?.state === 'running';
  const message = text => { if ($('pv-audio-status')) $('pv-audio-status').textContent = text; };
  function audioFailure(stage, status=0) {
    const error=new Error(stage);error.audioStage=stage;error.audioStatus=status;return error;
  }
  function reportFailure(error) {
    failed=true;
    const stage=error?.audioStage || 'track-playback', status=Number(error?.audioStatus)||0;
    const label=stage.startsWith('manifest')?'audio manifest':'audio track';
    if(status===401 || status===403) playbackMessage=`The server denied access to the ${label} (HTTP ${status}). Retry after access is restored.`;
    else if(status===404) playbackMessage=`The ${label} was not found on the server (HTTP 404). Restore the audio files, then retry.`;
    else if(stage==='manifest-invalid') playbackMessage='The audio manifest response is invalid. Retry after the audio files are restored.';
    else if(stage==='manifest-entry') playbackMessage='This music is missing from the audio manifest. Restore the matching audio files and retry.';
    else if(stage==='track-decode') playbackMessage='The audio track downloaded but could not be decoded. Retry playback to download it again.';
    else playbackMessage=`The ${label} could not load${status?` (HTTP ${status})`:''}. Please retry playback.`;
    message(playbackMessage);paint();
  }
  function loadManifest(force=false) {
    if(manifestLoaded) return Promise.resolve(true);
    if(manifestPending) return manifestPending;
    // A failed initial request is retried by the user, not by every focus event.
    if(manifestFailure && !force) return Promise.resolve(false);
    manifestPending=(async()=>{
      try {
        const response=await fetch(config.manifest,{credentials:'same-origin',cache:force?'reload':'no-cache'});
        if(!response.ok) throw audioFailure('manifest-download',response.status);
        let data;
        try {data=await response.json();} catch (_) {throw audioFailure('manifest-invalid');}
        const record=value=>value && typeof value==='object' && !Array.isArray(value);
        if(!record(data) || !['tracks','aliases','moves','types'].every(key=>record(data[key])) || !Object.keys(data.tracks).length)
          throw audioFailure('manifest-invalid');
        manifest=data;manifestLoaded=true;manifestFailure=null;failed=false;
        if(playbackMessage && $('pv-audio-status')?.textContent===playbackMessage) message('Audio files are ready.');
        playbackMessage='';paint();return true;
      } catch(error) {
        manifestFailure=error?.audioStage?error:audioFailure('manifest-download');
        reportFailure(manifestFailure);return false;
      } finally {manifestPending=null;}
    })();
    return manifestPending;
  }
  const later = (fn, ms, group='effects') => { const id=setTimeout(()=>{timers.delete(id);fn();},Math.max(0,ms));timers.set(id,group);return id; };
  function paint() {
    const toggle=$('pv-audio-toggle'); if (!toggle) return;
    toggle.disabled = !config.available || !(window.AudioContext || window.webkitAudioContext);
    toggle.setAttribute('aria-pressed',String(prefs.enabled));
    toggle.textContent = !config.available ? 'Sound unavailable' : prefs.enabled ? 'Sound on' : 'Sound off';
    toggle.title = prefs.enabled ? 'Mute music and sound effects' : 'Enable music and sound effects';
    const resume=$('pv-audio-resume');
    if(resume) {resume.hidden=!config.available || !prefs.enabled || (unlocked && !failed);resume.textContent=failed?'Retry playback':'Play sound';resume.title='Sound is enabled. Start playback if your browser paused it.';}
    $('pv-audio-details').disabled = false;
    for (const key of ['music','effects']) {
      $('pv-audio-'+key).value = String(prefs[key]);
      $('pv-audio-'+key+'-value').textContent = `${prefs[key]}%`;
    }
    $('pv-audio-track').textContent = manifest.tracks[desired]?.title ? `♪ ${manifest.tracks[desired].title}` : '';
  }
  function ramp(param, value, duration=.12) {
    if (!ctx) return;
    param.cancelScheduledValues(ctx.currentTime);
    param.setValueAtTime(param.value,ctx.currentTime);
    param.linearRampToValueAtTime(value,ctx.currentTime+duration);
  }
  function gains() {
    if (!ctx) return;
    ramp(master.gain, usable() ? 0.8 : 0, usable() ? .16 : .025);
    ramp(musicBus.gain, prefs.music/100);
    ramp(effectsBus.gain, prefs.effects/100);
  }
  function position() {
    if (!musicVoice || !ctx) return 0;
    const track=manifest.tracks[musicKey];
    let time=musicVoice.offset+Math.max(0,ctx.currentTime-musicVoice.started);
    const end=track?.loopEnd || musicVoice.source.buffer.duration, start=track?.loopStart || 0;
    if (time>=end && end>start) time=start+(time-start)%(end-start);
    return time;
  }
  function remember() {
    if (musicVoice) safeWrite('sessionStorage',scope+':position:'+musicKey,{at:Date.now(),offset:position()});
  }
  function stopVoice(voice) { try { voice.source.stop(); } catch (_) {} try { voice.source.disconnect();voice.gain.disconnect(); } catch (_) {} voices.delete(voice); }
  function cancelCues(includeMusic=true) { cueEpoch++; for (const [timer,group] of timers) if(includeMusic || group==='effects'){clearTimeout(timer);timers.delete(timer);} for (const v of [...voices]) if(v.bus===effectsBus) stopVoice(v); }
  function pause() {
    remember(); loadEpoch++;musicLoading=''; cancelCues();
    if(['won','lost','captured'].includes(config.context.battle?.status)) desired=config.context.battle.status==='lost'?'emerald.400':resultTrack(config.context.battle.status);
    if(musicVoice) stopVoice(musicVoice); musicVoice=null;musicKey='';
    gains();
  }
  function evict() {
    let size=[...buffers.values()].reduce((sum,b)=>sum+b.length*b.numberOfChannels*4,0);
    for (const [key,b] of buffers) {
      if(size<=96*1024*1024 && buffers.size<=28) break;
      if(key===musicKey) continue;
      buffers.delete(key);size-=b.length*b.numberOfChannels*4;
    }
  }
  function resolve(key) { return manifest.aliases[key] || key; }
  async function buffer(key) {
    key=resolve(key);
    if(buffers.has(key)) { const b=buffers.get(key);buffers.delete(key);buffers.set(key,b);return b; }
    if(pending.has(key)) return pending.get(key);
    const track=manifest.tracks[key];if(!track || typeof track.file!=='string' || !ctx) throw audioFailure('manifest-entry');
    const promise=(async()=>{
      const url=new URL(track.file,new URL(config.manifest,location.href));
      if(url.origin!==location.origin) throw new Error('Audio origin mismatch');
      const response=await fetch(url.href,{credentials:'same-origin',cache:failedTracks.has(key)?'reload':'force-cache'});
      if(!response.ok) throw audioFailure('track-download',response.status);
      const bytes=await response.arrayBuffer();
      let decoded;
      try {decoded=await ctx.decodeAudioData(bytes);} catch (_) {throw audioFailure('track-decode');}
      // Crystal recordings have no engine loop tags; only reviewed regions are blended.
      if(track.boundaryBlend || track.edgeFade) {
        const end=Math.min(decoded.length,Math.floor((track.loopEnd||decoded.duration)*decoded.sampleRate));
        const start=Math.floor((track.loopStart||0)*decoded.sampleRate);
        const blend=Math.min(Math.floor((track.boundaryBlend||track.edgeFade)*decoded.sampleRate),Math.floor(end/4));
        for(let ch=0;ch<decoded.numberOfChannels;ch++) {
          const samples=decoded.getChannelData(ch);
          for(let i=0;i<blend;i++) {
            const weight=(i+1)/blend;
            if(track.boundaryBlend && start>=blend) samples[end-blend+i]=samples[end-blend+i]*(1-weight)+samples[start-blend+i]*weight;
            else {samples[end-blend+i]*=1-weight;samples[i]*=weight;}
          }
        }
      }
      buffers.set(key,decoded);failedTracks.delete(key);evict();return decoded;
    })();
    pending.set(key,promise);
    try { return await promise; } catch(error) {failedTracks.add(key);throw error;} finally { pending.delete(key); }
  }
  function createVoice(decoded,bus,offset=0,loop=false,track={}) {
    const source=ctx.createBufferSource(), gain=ctx.createGain();
    source.buffer=decoded;source.loop=loop;
    if(loop) { source.loopStart=Math.max(0,track.loopStart||0);source.loopEnd=Math.min(decoded.duration,track.loopEnd||decoded.duration); }
    source.connect(gain);gain.connect(bus);
    const voice={source,gain,offset,started:ctx.currentTime,bus};voices.add(voice);
    source.onended=()=>{voices.delete(voice);try{source.disconnect();gain.disconnect();}catch(_){}};
    try {source.start(0,Math.min(Math.max(0,offset),decoded.duration-.001));}
    catch(error) {stopVoice(voice);throw error;}
    return voice;
  }
  async function setMusic(key) {
    desired=resolve(key);paint();
    if(!manifestLoaded || !usable() || prefs.music===0 || musicKey===desired || musicLoading===desired) return;
    const epoch=++loadEpoch, requested=desired;musicLoading=requested;
    try {
      const decoded=await buffer(requested);
      if(epoch!==loadEpoch || !usable() || requested!==desired || prefs.music===0) return;
      remember(); const previous=musicVoice;
      const savedPosition=safeRead('sessionStorage',scope+':position:'+requested,null);
      const offset=savedPosition && Date.now()-savedPosition.at<2*60*60*1000 ? Number(savedPosition.offset)||0 : 0;
      const nextVoice=createVoice(decoded,musicBus,requested==='jingle.391'?0:offset,requested!=='jingle.391',manifest.tracks[requested]);
      musicKey=requested;musicVoice=nextVoice;
      if(requested==='jingle.391') {
        const voice=musicVoice, cleanup=voice.source.onended;
        voice.source.onended=()=>{cleanup();if(musicVoice===voice){musicVoice=null;musicKey='';void setMusic('emerald.400');}};
      }
      musicVoice.gain.gain.setValueAtTime(0,ctx.currentTime);ramp(musicVoice.gain.gain,1,.32);
      if(previous) { ramp(previous.gain.gain,0,.32);setTimeout(()=>stopVoice(previous),360); }
      if(playbackMessage && $('pv-audio-status')?.textContent===playbackMessage) message('Music is playing.');
      playbackMessage='';failed=false;paint();evict();
    } catch(error) {
      if(epoch===loadEpoch) reportFailure(error);
    } finally { if(epoch===loadEpoch) musicLoading=''; }
  }
  async function unlock(fromGesture=false) {
    if(!config.available || !prefs.enabled || document.hidden || gone) return;
    try {
      if(activationAttempt && !fromGesture) return;
      if(!ctx) {
        const Context=window.AudioContext || window.webkitAudioContext;if(!Context) return;
        ctx=new Context(); master=ctx.createGain();musicBus=ctx.createGain();effectsBus=ctx.createGain();
        master.gain.value=0;musicBus.gain.value=prefs.music/100;effectsBus.gain.value=prefs.effects/100;
        musicBus.connect(master);effectsBus.connect(master);
        if(typeof ctx.createDynamicsCompressor==='function') {
          const limiter=ctx.createDynamicsCompressor();limiter.threshold.value=-6;limiter.knee.value=4;limiter.ratio.value=12;limiter.attack.value=.005;limiter.release.value=.12;master.connect(limiter);limiter.connect(ctx.destination);
        } else master.connect(ctx.destination);
        ctx.onstatechange=()=>{unlocked=ctx.state==='running';gains();paint();if(unlocked && manifestLoaded)void setMusic(desired);};
      }
      // Called directly during trusted gestures, with an automatic best-effort resume on navigation.
      activationAttempt=true;
      const resume=ctx.resume();
      setTimeout(()=>{activationAttempt=false;},120);
      await resume;
      activationAttempt=false;
      unlocked=ctx.state==='running';gains();paint();
      const loaded=await loadManifest(fromGesture);
      if(loaded && usable()) void setMusic(desired);
    } catch (_) { activationAttempt=false;unlocked=false;message('Sound is on. Your browser may require Play sound or permission to autoplay.');paint(); }
  }
  function claim(key) {
    if(!key || claims.has(key)) return false;
    claims.add(key);while(claims.size>160) claims.delete(claims.values().next().value);
    safeWrite('sessionStorage',scope+':events',[...claims]);return true;
  }
  function cue(key,delay=0) {
    key=resolve(key);const track=manifest.tracks[key];
    if(!track || !usable() || prefs.effects===0) return;
    const epoch=cueEpoch, due=performance.now()+Math.max(0,delay)*1000;
    const loaded=buffer(key).catch(()=>null);
    later(async()=>{
      const decoded=await loaded;
      if(!decoded || epoch!==cueEpoch || !usable() || prefs.effects===0 || performance.now()-due>350) return;
      const effects=[...voices].filter(v=>v.bus===effectsBus);
      if(effects.length>=8) stopVoice(effects[0]);
      const voice=createVoice(decoded,effectsBus);
      // Loop tags on Gust / low-HP effects never turn an effect into an infinite sound.
      const duration=Math.min(decoded.duration,track.effectLimit||3);
      if(duration<decoded.duration) {voice.gain.gain.setValueAtTime(1,ctx.currentTime+Math.max(0,duration-.08));voice.gain.gain.linearRampToValueAtTime(0,ctx.currentTime+duration);voice.source.stop(ctx.currentTime+duration);}
    },Math.max(0,delay)*1000);
  }
  function strike(data, delay=0) {
    if(!data) return;
    const normalized=String(data.move||'').toLowerCase().replace(/[^a-z0-9]/g,'');
    const key=manifest.moves[normalized] || manifest.types[String(data.type||'normal').toLowerCase()] || 'sfx.152';
    cue(key,delay);
    if(data.hit===false) cue('miss',delay+.205);
    else if(Number(data.damage)>0 && Number(data.effectiveness)!==0) cue(Number(data.effectiveness)>=2?'hit_super':Number(data.effectiveness)<1?'hit_weak':'hit',delay+.205);
  }
  function resultTrack(kind) {
    const battle=config.context.battle||{};
    return kind==='captured'?'jingle.352':kind==='lost'?'jingle.391':
      battle.kind==='wild'?'jingle.353':['gym','frontier'].includes(battle.kind)?'jingle.354':
      ['champion','elite'].includes(battle.kind)?'jingle.355':battle.kind==='team'?'jingle.424':'jingle.412';
  }
  function result(kind, delay=0) {
    // Results are music; the short ball-click cue remains in the effects channel.
    later(()=>{void setMusic(resultTrack(kind));},Math.max(0,delay)*1000,'music');
  }
  const battle=config.context.battle||{};
  const terminal=['won','lost','captured'].includes(battle.status);
  const resultClaim=`battle:${battle.mode}:${battle.id}:result:${battle.status}`;
  if(terminal && claims.has(resultClaim)) desired=battle.status==='lost'?'emerald.400':resultTrack(battle.status);
  const fields=['enabled','music','effects'];
  const pendingKey=scope+':pending';
  let memoryJournal=null;
  function journalRecord() {
    // Session storage remains useful if local storage is readable but full or
    // unwritable. The in-memory copy keeps this page usable if both are blocked.
    return [safeRead('localStorage',pendingKey,null),safeRead('sessionStorage',pendingKey,null),memoryJournal]
      .filter(Boolean).sort((a,b)=>(Number(b.updatedAt??b.at)||0)-(Number(a.updatedAt??a.at)||0))[0];
  }
  function journal() {
    const record=journalRecord();
    const edits={};
    for(const key of fields) {
      let entry=record?.edits?.[key];
      // Carry forward explicit edits left by v32, without its one-minute expiry.
      if(!entry && record?.values && Object.prototype.hasOwnProperty.call(record.values,key)) entry={id:`v32:${record.at}:${key}`,value:record.values[key]};
      if(entry && typeof entry.id==='string') edits[key]={id:entry.id,value:key==='enabled'?![false,0,'0'].includes(entry.value):clamp(entry.value)};
    }
    return edits;
  }
  function writeJournal(edits) {
    const previous=journalRecord();
    const record={edits,updatedAt:Math.max(Date.now(),(Number(previous?.updatedAt??previous?.at)||0)+1)};
    memoryJournal=record;
    safeWrite('localStorage',pendingKey,record);
    safeWrite('sessionStorage',pendingKey,record);
  }
  function loadEdits() {
    const edits=journal();touched.clear();prefs={...saved};
    if(config.account) for(const key of fields) if(edits[key]) {touched.set(key,edits[key]);prefs[key]=edits[key].value;}
    dirty=touched.size>0;
  }
  function canonical(incoming) {
    let next=clean(incoming);
    const previous=safeRead('localStorage',scope,null);
    if(config.account) {
      if(saved.revision>next.revision)next={...saved};
      if(previous && Number(previous.revision)>next.revision)next=clean(previous);
    }
    saved=next;
    if(!previous || Number(previous.revision)<=saved.revision) safeWrite('localStorage',scope,saved);
    loadEdits();
  }
  function apply(incoming) {
    canonical(incoming);
    if(!prefs.enabled) pause();else{gains();void unlock();}paint();
  }
  function sessionChanged() {
    pause();config.available=false;paint();
    message('Your signed-in account changed. Refresh the page to continue.');
  }
  function responseForAccount(data) {
    if(Number(data.account)!==Number(config.account)) {sessionChanged();throw new Error('Refresh the page for your current account.');}
    if(typeof data.csrf==='string' && data.csrf)config.csrf=data.csrf;
    return data;
  }
  async function getSettings() {
    const r=await fetch(config.endpoint,{credentials:'same-origin',cache:'no-store'});
    const data=await r.json();
    if(r.status===401) {sessionChanged();throw new Error('Sign in again to save sound settings.');}
    if(!r.ok || !data.ok)throw new Error(data.error||'Sound settings could not be loaded.');
    return responseForAccount(data);
  }
  loadEdits();
  async function save() {
    clearTimeout(saveTimer);
    if(saving || !config.available) return;
    if(!config.account) {
      if(!dirty)return;
      dirty=false;saved={...prefs};safeWrite('localStorage',scope,prefs);
      broadcast({type:'settings',preferences:prefs});message('Saved on this device.');return;
    }
    loadEdits();if(!dirty)return;
    saving=true;const submitted=new Map(touched), snapshot={...prefs};
    message('Saving sound settings…');
    let retry=false;
    try {
      const post=()=>fetch(config.endpoint,{method:'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},keepalive:true,
        body:new URLSearchParams({csrf_token:config.csrf,expected_account:String(config.account),enabled:snapshot.enabled?'1':'0',music:String(snapshot.music),effects:String(snapshot.effects),revision:String(saved.revision)})});
      let response=await post();
      if(response.status===403) {
        // Refresh an expired token only inside the same authenticated account.
        await getSettings();response=await post();
        if(response.status===403) {
          const error=new Error('Refresh the page before saving sound settings.');
          error.retryable=false;throw error;
        }
      }
      const data=await response.json();
      if(response.status===401 || data.account_mismatch) {sessionChanged();throw new Error('Sign in again or refresh for your current account.');}
      responseForAccount(data);
      if(response.status===409 && data.preferences) {
        canonical(data.preferences);conflicts++;retry=conflicts<3;
        message('Syncing your latest sound choice…');
      } else {
        if(!response.ok || !data.ok)throw new Error(data.error||'Sound settings could not be saved.');
        const edits=journal();
        // A previous page's late response cannot clear a newer click's pending edit.
        for(const [key,entry] of submitted)if(edits[key]?.id===entry.id)delete edits[key];
        writeJournal(edits);canonical(data.preferences);retries=0;conflicts=0;
        broadcast({type:'settings',preferences:saved});
        message(dirty?'Saving your latest sound choice…':'Saved to your account.');
        retry=dirty;
      }
    } catch(error) {
      // Keep the account-scoped journal until an acknowledged write; never let a
      // focus refresh, delayed response, or new page erase an unsaved choice.
      loadEdits();retries++;retry=error.retryable!==false && retries<3;
      message(`${error.message} Your choice is kept on this device and will sync when available.`);
    } finally {
      saving=false;gains();paint();
      if(dirty && retry && !gone && config.available)saveTimer=setTimeout(save,retries?Math.min(5000,retries*1000):200);
    }
  }
  async function refresh() {
    if(!config.account || saving || gone || !config.available) return;
    loadEdits();
    if(dirty) {retries=0;conflicts=0;void save();return;}
    try {
      const data=await getSettings();
      // apply() merges any local edit created while this GET was in flight.
      if(!saving)apply(data.preferences);
    } catch (_) {}
  }
  function edited(field) {
    editCount++;conflicts=0;retries=0;
    if(config.account) {
      const edits=journal();edits[field]={id:`${instance}:${editCount}`,value:prefs[field]};writeJournal(edits);loadEdits();
    } else dirty=true;
    gains();paint();clearTimeout(saveTimer);saveTimer=setTimeout(save,180);
  }
  const ready=loadManifest();
  let settleInitialResume;
  const initialResume=new Promise(resolve=>{settleInitialResume=resolve;});
  window.PVAudio={ready:Promise.all([ready,initialResume]),claim,cue,strike,result,setMusic,
    restoreResult:kind=>setMusic(kind==='lost'?'emerald.400':resultTrack(kind)),prepare:keys=>{
    if(!usable()) return Promise.resolve([]);
    return Promise.allSettled(keys.slice(0,24).map(key=>buffer(resolve(key))));
  }};
  function init() {
    paint();
    $('pv-audio-toggle')?.addEventListener('click',()=>{
      prefs.enabled=!prefs.enabled;edited('enabled');
      if(prefs.enabled) void unlock(true);else pause();void save();
    });
    $('pv-audio-resume')?.addEventListener('click',()=>void unlock(true));
    $('pv-audio-details')?.addEventListener('click',()=>{const p=$('pv-audio-panel');p.hidden=!p.hidden;$('pv-audio-details').setAttribute('aria-expanded',String(!p.hidden));});
    document.addEventListener('keydown',e=>{if(e.key==='Escape' && !$('pv-audio-panel').hidden){$('pv-audio-panel').hidden=true;$('pv-audio-details').setAttribute('aria-expanded','false');$('pv-audio-details').focus();}});
    document.addEventListener('pointerdown',e=>{if(!e.target.closest?.('#pv-audio-dock') && !$('pv-audio-panel').hidden){$('pv-audio-panel').hidden=true;$('pv-audio-details').setAttribute('aria-expanded','false');}});
    for(const key of ['music','effects']) {
      $('pv-audio-'+key)?.addEventListener('input',e=>{
        prefs[key]=clamp(e.target.value);edited(key);
        if(key==='music') {if(prefs.music===0){remember();if(musicVoice)stopVoice(musicVoice);musicVoice=null;musicKey='';}else void setMusic(desired);}
        if(key==='effects' && prefs.effects===0)cancelCues(false);
      });
      $('pv-audio-'+key)?.addEventListener('change',()=>void save());
    }
    const gesture=e=>{if(e.isTrusted && !e.target.closest?.('#pv-audio-toggle') && (!unlocked || ctx?.state!=='running'))void unlock(true);};
    for(const event of ['pointerdown','pointerup','click','keydown'])document.addEventListener(event,gesture,{passive:true});
    if(dirty)void save();else void refresh();
    // Wait briefly for this document's automatic resume, never for a later gesture.
    const firstUnlock=unlock();
    // Recover delayed browser activation without changing the stored setting.
    for(const delay of [160,500,1200])setTimeout(()=>{if(!gone && !unlocked)void unlock();},delay);
    Promise.race([firstUnlock,new Promise(resolve=>setTimeout(resolve,180))]).then(()=>settleInitialResume());
  }
  if(channel) channel.onmessage=e=>{
    const msg=e.data;if(!msg || msg.instance===instance)return;
    if(msg.type==='settings' && msg.preferences && !saving && (!config.account || Number(msg.preferences.revision)>saved.revision))apply(msg.preferences);
  };
  window.addEventListener('storage',e=>{if(e.key===pendingKey && !saving){loadEdits();if(!prefs.enabled)pause();else void unlock();paint();}if(e.key===scope && !saving){const p=safeRead('localStorage',scope,null);if(p && (!config.account || Number(p.revision)>saved.revision))apply(p);}});
  document.addEventListener('visibilitychange',()=>{if(document.hidden){pause();void save();}else{void refresh();void unlock();}});
  window.addEventListener('focus',()=>{void refresh();void unlock();});
  window.addEventListener('pagehide',()=>{void save();gone=true;pause();});
  window.addEventListener('pageshow',()=>{gone=false;void refresh();void unlock();});
  window.addEventListener('load',()=>{void unlock();});
  window.addEventListener('online',()=>{retries=0;conflicts=0;void refresh();});
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
