const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
const check = (condition) => { assert.ok(condition); checks++; };
class Element {
    constructor(tag = 'div', text = '') { this.tagName = tag; this.text = text; this.children = []; this.dataset = {}; this.style = {}; }
    get textContent() { return this.text + this.children.map(child => child.textContent).join(''); }
    set textContent(value) { this.text = String(value); this.children = []; }
    set innerHTML(value) { throw new Error('Search must not parse employee or query text as HTML'); }
    get firstElementChild() { return this.children[0]; }
    appendChild(child) { this.children.push(child); return child; }
    replaceChildren() { this.children = []; this.text = ''; }
    addEventListener() {}
    focus() {}
    querySelectorAll() { return this.fields || []; }
}
const row = new Element('tr');
row.dataset = {empNumber:'test001', staffName:'test <img src=x onerror=alert(1)>'};
row.fields = [new Element('span','TEST001'), new Element('span','Test <img src=x onerror=alert(1)>')];
const ids = Object.fromEntries(['searchInput','registrationsTableBody','resultCount','searchTerm','searchResultsInfo','loadingIndicator'].map(id => [id, new Element()]));
ids.registrationsTableBody.appendChild(row);
const ready = [];
const document = {getElementById: id => ids[id], querySelectorAll: () => [row], addEventListener: (event, callback) => { if (event === 'DOMContentLoaded') ready.push(callback); }, createElement: tag => new Element(tag), createTextNode: text => new Element('#text',text)};
const context = {document, window:{location:{reload(){}}}, setTimeout, clearTimeout};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../public/assets/js/admin-dashboard.js'),'utf8'), context);
ready.forEach(callback => callback());
context.performSearch('no match');
check(row.style.display === 'none');
check(ids.registrationsTableBody.children.includes(row));
context.clearSearch();
check(row.style.display === '');
check(ids.registrationsTableBody.children[1].hidden);
context.performSearch('<img');
check(row.fields[1].textContent === 'Test <img src=x onerror=alert(1)>');
check(row.fields[1].children.some(child => child.tagName === 'mark'));
context.performSearch('<script>alert(1)</script>');
check(ids.registrationsTableBody.children[1].firstElementChild.textContent.includes('<script>'));
// Every admin tab loads the common script; missing seat controls must be harmless.
const handlers = [];
const admin = {document:{getElementById:()=>null, addEventListener:(event,callback)=>handlers.push(callback)}, window:{MovieNightPage:{currentTab:'settings'}}};
vm.createContext(admin);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../public/assets/js/admin.js'),'utf8'), admin);
handlers.forEach(callback => callback());
check(admin.escapeHtml('<img> & "') === '&lt;img&gt; &amp; &quot;');
const messages = [];
const limitInput = {value:'', focus(){}};
admin.document.getElementById = () => limitInput;
admin.showToast = message => messages.push(message);
admin.fetch = () => { throw new Error('Invalid attendee limits must not be submitted'); };
for (const value of ['', '0', '11', '1.5', '1e1']) {
    limitInput.value = value;
    admin.saveSetting('max_attendees');
    check(messages.at(-1) === 'Attendee limit must be a whole number between 1 and 10.');
}
console.log('PASS: ' + checks + ' admin UI checks');
