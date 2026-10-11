/*
 * The platform's side of every game (Platform\Games\GamePage puts it first
 * in the page). A game talks to the screen that opened it only through
 * window.LMS:
 *
 *   LMS.onStart(function (round) { ... })   round.items (each with dir: the way its words read), round.stage, round.locale, round.dir, round.title
 *   LMS.answer(item.id, true|false)        every try at an item
 *   LMS.finish()                            the round is over (the screen shows the stars)
 *   LMS.muted                               true while sound is off
 *   LMS.sfx('tap'|'correct'|'wrong'|'pop'|'win'|'whoosh'|'step')
 *   LMS.tone(freq, seconds, type, volume, delay)
 *   LMS.shuffle(list)                       a shuffled copy
 *
 * Items come from the learner's curriculum, never from the game:
 *   {id, kind: 'choice', prompt, options: [..], answer: index}
 *   {id, kind: 'order', prompt, parts: [.. in the right order]}
 *   {id, kind: 'pair', left, right}
 *
 * In a browser the page sits in a sandboxed frame and speaks with
 * postMessage; in the app's WebView it speaks through the LmsHost channel
 * and hears through LMS.receive().
 */
(function () {
  'use strict';

  var round = null;
  var startHandlers = [];
  var finished = false;
  var muted = false;
  var audio = null;

  function send(message) {
    message.lms = 1;
    var text = JSON.stringify(message);
    if (window.LmsHost && typeof window.LmsHost.postMessage === 'function') {
      window.LmsHost.postMessage(text);
    } else if (window.parent && window.parent !== window) {
      window.parent.postMessage(text, '*');
    }
  }

  function receive(data) {
    var message = data;
    if (typeof data === 'string') {
      try { message = JSON.parse(data); } catch (e) { return; }
    }
    if (!message || message.lms !== 1) return;
    if (message.type === 'start' && round === null && message.round && Array.isArray(message.round.items)) {
      round = message.round;
      muted = !!round.muted;
      document.documentElement.lang = round.locale || 'ar';
      document.documentElement.dir = round.dir || 'rtl';
      // The practice words may be in another language than the screen (an English lesson in an
      // Arabic app): every line of text reads the way its own words do (item.dir says it too).
      var bidi = document.createElement('style');
      bidi.textContent = 'body, body * { unicode-bidi: plaintext; }';
      (document.head || document.documentElement).appendChild(bidi);
      startHandlers.forEach(function (handler) { run(handler); });
    } else if (message.type === 'mute') {
      muted = !!message.muted;
    }
  }

  function run(handler) {
    try { handler(round); } catch (e) { report(e && e.message, 0); }
  }

  function report(text, line) {
    send({ type: 'error', message: String(text || 'error').slice(0, 300), line: line || 0 });
  }

  function context() {
    if (muted) return null;
    if (!audio) {
      var Context = window.AudioContext || window.webkitAudioContext;
      if (!Context) return null;
      try { audio = new Context(); } catch (e) { return null; }
    }
    if (audio.state === 'suspended') audio.resume();
    return audio;
  }

  function tone(freq, seconds, type, volume, delay) {
    var ctx = context();
    if (!ctx) return;
    var at = ctx.currentTime + (delay || 0);
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = type || 'sine';
    osc.frequency.setValueAtTime(freq, at);
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(volume || 0.18, at + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + seconds);
    osc.connect(gain).connect(ctx.destination);
    osc.start(at);
    osc.stop(at + seconds + 0.05);
  }

  function slide(from, to, seconds, type, volume, delay) {
    var ctx = context();
    if (!ctx) return;
    var at = ctx.currentTime + (delay || 0);
    var osc = ctx.createOscillator();
    var gain = ctx.createGain();
    osc.type = type || 'sine';
    osc.frequency.setValueAtTime(from, at);
    osc.frequency.exponentialRampToValueAtTime(to, at + seconds);
    gain.gain.setValueAtTime(volume || 0.15, at);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + seconds);
    osc.connect(gain).connect(ctx.destination);
    osc.start(at);
    osc.stop(at + seconds + 0.05);
  }

  function noise(seconds, volume, delay, high) {
    var ctx = context();
    if (!ctx) return;
    var at = ctx.currentTime + (delay || 0);
    var length = Math.max(1, Math.floor(ctx.sampleRate * seconds));
    var buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    var data = buffer.getChannelData(0);
    for (var i = 0; i < length; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / length);
    var source = ctx.createBufferSource();
    var filter = ctx.createBiquadFilter();
    var gain = ctx.createGain();
    source.buffer = buffer;
    filter.type = high ? 'highpass' : 'bandpass';
    filter.frequency.value = high ? 2000 : 900;
    gain.gain.value = volume || 0.12;
    source.connect(filter).connect(gain).connect(ctx.destination);
    source.start(at);
  }

  var effects = {
    tap: function () { tone(880, 0.07, 'sine', 0.12); },
    step: function () { tone(520 + Math.random() * 80, 0.06, 'triangle', 0.1); },
    correct: function () { tone(523, 0.14, 'triangle', 0.2); tone(659, 0.14, 'triangle', 0.2, 0.09); tone(784, 0.26, 'triangle', 0.2, 0.18); },
    wrong: function () { slide(300, 150, 0.28, 'square', 0.06); },
    pop: function () { noise(0.12, 0.16, 0, true); slide(900, 1500, 0.08, 'sine', 0.12); },
    whoosh: function () { noise(0.35, 0.1); },
    win: function () {
      [523, 659, 784, 1047].forEach(function (f, i) { tone(f, 0.22, 'triangle', 0.18, i * 0.11); });
      tone(1319, 0.5, 'sine', 0.14, 0.48);
      noise(0.4, 0.05, 0.48, true);
    }
  };

  window.addEventListener('message', function (event) {
    if (event.source !== window.parent) return;
    receive(event.data);
  });
  window.addEventListener('error', function (event) { report(event.message, event.lineno); });
  window.addEventListener('unhandledrejection', function (event) { report(event.reason && event.reason.message); });
  window.addEventListener('load', function () { send({ type: 'ready' }); });

  window.LMS = Object.freeze({
    onStart: function (handler) {
      if (typeof handler !== 'function') return;
      startHandlers.push(handler);
      if (round !== null) run(handler);
    },
    answer: function (itemId, correct) {
      if (finished || round === null) return;
      send({ type: 'answer', item: String(itemId), correct: correct === true });
    },
    finish: function () {
      if (finished || round === null) return;
      finished = true;
      send({ type: 'finish' });
    },
    get muted() { return muted; },
    sfx: function (name) { if (effects[name]) effects[name](); },
    tone: tone,
    shuffle: function (list) {
      var copy = Array.prototype.slice.call(list || []);
      for (var i = copy.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var t = copy[i]; copy[i] = copy[j]; copy[j] = t;
      }
      return copy;
    },
    receive: receive
  });
})();
