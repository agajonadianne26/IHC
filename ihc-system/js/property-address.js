/* =====================================================================
   IHC — property address parsing (single source of truth)

   Turns a free-typed full address into its Phase / Block / Lot /
   Project parts, e.g.

     "Phase 3, Block 12, Lot 5, Mabuhay Street, Soldier's Hills
      Subdivision, Barangay Molino VI, Bacoor City, Cavite"

   -> { phase: 'Phase 3', block: 'Block 12', lot: 'Lot 5',
        project: "Soldier's Hills Subdivision" }

   Consumers:
     - html/clerk-dashboard.html  New Contract form (address autofill)
     - html/admin-dashboard.html  Property Inventory Add / Edit modal

   Keep ONE implementation here: the parts written back are what the
   clerk New Contract form matches on, so both dashboards must split an
   address the same way.
   ===================================================================== */
(function () {
  'use strict';

  // A part keyword (phase / blk / lot ...) must be followed by a real
  // separator or a digit, so ordinary words such as "Philippines" or
  // "Sophia" are never mistaken for a phase. "Phase 3", "Ph.2" and
  // "Block12" all match; "blockhouse" and "lotus" do not.
  function partPattern(keyword) {
    return new RegExp('\\b(?:' + keyword + ')(?:[\\s.:-]+|(?=\\d))([\\w-]+)', 'i');
  }

  const PHASE_RE = partPattern('phase|ph');
  const BLOCK_RE = partPattern('block|blk');
  const LOT_RE = partPattern('lot');

  // A segment that names the development/subdivision is the project.
  const PROJECT_RE = /subdivision|village|homes|estates|residences|township|subd\.?/i;

  // Strips part tokens ("Block 12", "Phase 3", ...) out of a segment so
  // e.g. "Test Village - Block 7 Lot 3" yields the project "Test Village".
  function stripParts(segment) {
    return String(segment)
      .replace(PHASE_RE, ' ')
      .replace(BLOCK_RE, ' ')
      .replace(LOT_RE, ' ')
      .replace(/^[\s,;:\-–—]+|[\s,;:\-–—]+$/g, '')
      .replace(/\s{2,}/g, ' ')
      .trim();
  }

  function parse(text) {
    const out = {};
    const src = String(text == null ? '' : text);

    const phase = src.match(PHASE_RE);
    if (phase) out.phase = 'Phase ' + phase[1];

    const block = src.match(BLOCK_RE);
    if (block) out.block = 'Block ' + block[1];

    const lot = src.match(LOT_RE);
    if (lot) out.lot = 'Lot ' + lot[1];

    const segment = src.split(',').map((s) => s.trim()).find((s) => PROJECT_RE.test(s));
    if (segment) {
      const project = stripParts(segment);
      if (project) out.project = project;
    }
    return out;
  }

  // Fills the part fields from `text`. Never overwrites a hand-typed
  // value: only an empty field, or one auto-filled by this helper
  // (marked with data-addr-auto), is updated. Returns the elements whose
  // value actually changed so the caller can flash / revalidate.
  function autofill(fields, text) {
    const parts = parse(text);
    const changed = [];
    const targets = [
      [fields && fields.phase, parts.phase],
      [fields && fields.block, parts.block],
      [fields && fields.lot, parts.lot],
      [fields && fields.project, parts.project]
    ];
    targets.forEach(([el, val]) => {
      if (!el || !val) return;
      // Never overwrite something the user typed by hand.
      if (el.value.trim() !== '' && el.dataset.addrAuto !== '1') return;
      if (el.value !== val) {
        el.value = val;
        changed.push(el);
      }
      el.dataset.addrAuto = '1';
    });
    return changed;
  }

  // Typing in a part field by hand detaches it from the address parse,
  // so a later address edit won't stomp it.
  function detach(el) {
    if (el) delete el.dataset.addrAuto;
  }

  // Clears every auto-fill mark (called when a form/modal opens).
  function reset(fields) {
    Object.keys(fields || {}).forEach((key) => detach(fields[key]));
  }

  window.IHCPropertyAddress = { parse, autofill, detach, reset };
})();
