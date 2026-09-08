// Lightweight DOM doubles: no network, credentials or installed WordPress needed.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
  constructor() { this.attrs = {}; this.handlers = {}; this.innerHTML = ''; this.hidden = false; this.dataset = {}; this.classList = {add() {}, remove() {}}; }
  getAttribute(k) { return this.attrs[k] || null; }
  setAttribute(k, v) { this.attrs[k] = v; }
  removeAttribute(k) { delete this.attrs[k]; }
  addEventListener(k, fn) { this.handlers[k] = fn; }
  focus() {}
  querySelectorAll() { return []; }
  insertAdjacentHTML(_, html) { this.innerHTML += html; }
  insertAdjacentElement(_, el) { this.after = el; }
}
const root = new Element();
const grid = new Element(), status = new Element(), form = new Element(), search = new Element(), more = new Element(), detail = new Element(), progress = new Element(), progressText = new Element(), progressDetail = new Element();
const mapping = {'[data-mcf-recipe-grid]': grid, '[data-mcf-recipe-detail]': detail, '.mcf-recipe-status': status, '[data-mcf-search-progress]': progress, '[data-mcf-search-progress-text]': progressText, '[data-mcf-search-progress-detail]': progressDetail, '.mcf-recipe-search': form, 'input[name="search"]': search, '.mcf-recipe-load-more': more};
root.querySelector = s => mapping[s] || null;
const pending = [];
const context = {
  window: { MCFRecipes: {restUrl: '/wp-json/mcf-recipes/v1', i18n: {loading: 'Loading', noResults: 'None', aiSearching: 'Finding recipes', aiSearchError: 'AI search failed'} }, setTimeout, clearTimeout},
  document: {readyState: 'complete', querySelectorAll: () => [root], createElement: () => new Element(), addEventListener() {}},
  fetch: url => new Promise((resolve, reject) => pending.push({url, resolve, reject})),
  URLSearchParams
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/js/mcf-recipes.js'), 'utf8'), context);
const flush = () => new Promise(resolve => setImmediate(resolve));
async function reply(data, request = pending.shift()) { request.resolve({ok: true, json: async () => data}); await flush(); }
function submit(value) { search.value = value; form.handlers.submit({preventDefault() {}}); }
const recipe = (id, title) => ({id, title});
(async () => {
  await reply({recipes: [], total: 0, pages: 0});
  assert.equal(more.hidden, true);
  submit('carrot');
  assert.match(pending[0].url, /ai=1/);
  assert.equal(progress.hidden, false);
  assert.match(progressText.textContent, /Finding recipes/);
  await reply({recipes: [recipe(1, 'Carrot soup')], other_recipes: [recipe(2, 'Lasagne')], total: 1, pages: 1, search_status: 'ok', search_key: 'query-key'});
  assert.equal(progress.hidden, true);
  assert.match(grid.innerHTML, /Carrot soup/);
  assert.doesNotMatch(grid.innerHTML, /Lasagne/);
  assert.match(more.after.innerHTML, /Other recipes containing your ingredients/);
  assert.match(more.after.innerHTML, /data-mcf-view="2"/);
  assert.equal(more.after.open, false);
  root.handlers.click({target: {closest(selector) { return selector === '[data-mcf-view]' ? {getAttribute() { return '1'; }} : null; }}});
  assert.match(pending[0].url, /\/recipes\/1/);
  await reply(recipe(1, 'Carrot soup'));
  assert.match(pending[0].url, /\/recipes\/1\/click/);
  const clickRequest = pending.shift();
  clickRequest.resolve({ok: true, json: async () => ({recorded: true})});
  await flush();
  submit('carrot');
  await reply({recipes: [], other_recipes: [], total: 0, pages: 0, search_status: 'no_strong_matches'});
  assert.equal(grid.innerHTML, '');
  assert.equal(more.after.hidden, true);
  assert.match(status.textContent, /No recipes focused/);
  submit('carrot');
  await reply({recipes: [], total: 0, pages: 0, search_status: 'ai_error'});
  assert.match(status.textContent, /AI search failed/);
  submit('carrot');
  pending.shift().reject(new Error('network'));
  await flush();
  assert.match(status.textContent, /could not be loaded/);
  submit('carrot'); const stale = pending.shift();
  submit('beef');
  await reply({recipes: [recipe(3, 'Beef stew')], total: 1, pages: 1});
  await reply({recipes: [recipe(1, 'Carrot soup')], total: 1, pages: 1}, stale);
  assert.match(grid.innerHTML, /Beef stew/);
  assert.doesNotMatch(grid.innerHTML, /Carrot soup/);
  submit('carrot');
  await reply({recipes: [recipe(1, '<script>alert(1)</script>')], total: 1, pages: 1});
  assert.doesNotMatch(grid.innerHTML, /<script>/);
  console.log('PASS: progress feedback, separated results, valid empty state, AI error, network error, stale-response guard, HTML escaping');
})().catch(error => { console.error(error); process.exitCode = 1; });
