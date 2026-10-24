/* =========================================================
   Barter Hub - front-end behaviour (plain JavaScript)
   This file only makes the pages FEEL alive for the demo.
   Nothing is saved on a server. In the backend step, PHP will
   do the real work and most of these demo parts get replaced.
   ========================================================= */
(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var params = new URLSearchParams(window.location.search);

  /* 1. Show alerts that depend on the URL, e.g. ?added=1 ------------- */
  $$('[data-show-param]').forEach(function (el) {
    if (params.has(el.getAttribute('data-show-param'))) el.classList.remove('d-none');
  });

  /* 2. Bootstrap form validation ------------------------------------ */
  $$('form.needs-validation').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
      form.classList.add('was-validated');
    });
  });


  /* ---- Demo storage (browser only). Backend step: this becomes MySQL. ----
     Item life cycle:  pending (waiting for admin)  ->  approved (public)  or  rejected  */
  function getItems() {
    var l = [];
    try { l = JSON.parse(localStorage.getItem('bh_new_items') || '[]'); } catch (err) {}
    return l.map(function (it, i) {
      if (!it.id) it.id = 'old' + i;
      if (!it.status) it.status = 'pending';   // items from the older demo version must also be reviewed
      return it;
    });
  }
  function saveItems(l) { try { localStorage.setItem('bh_new_items', JSON.stringify(l)); } catch (err) {} }
  function getSent() { try { return JSON.parse(localStorage.getItem('bh_sent_requests') || '[]'); } catch (err) { return []; } }
  var iconFor = { Books: 'book', Electronics: 'laptop', Furniture: 'lamp', Clothing: 'handbag', Sports: 'trophy', Kitchen: 'cup-hot', Toys: 'controller', Mobiles: 'phone' };

  /* ---- Show user-added items -------------------------------------------
     data-new-items="public" -> ONLY items the admin approved (home, browse, dashboard)
     data-new-items="mine"   -> all of my items, with their review status  */
  $$('[data-new-items]').forEach(function (host) {
    var mode = host.getAttribute('data-new-items');
    getItems().filter(function (it) { return mode === 'mine' || it.status === 'approved'; }).forEach(function (it) {
      var col = document.createElement('div');
      col.className = 'col-sm-6 col-lg-4 item-col';
      col.dataset.title = it.title; col.dataset.cat = it.cat; col.dataset.cond = it.cond;
      col.dataset.city = it.city; col.dataset.wants = it.wants;
      var statusBadge = '';
      var foot = '<div class="card-footer bg-white border-0"><a class="btn btn-outline-primary btn-sm w-100" href="item.html?id=' + encodeURIComponent(it.id) + '">View details</a></div>';
      if (mode === 'mine') {
        if (it.status === 'pending') statusBadge = '<span class="badge text-bg-warning float-end"><i class="bi bi-hourglass-split"></i> Waiting for admin</span>';
        if (it.status === 'approved') statusBadge = '<span class="badge text-bg-success float-end"><i class="bi bi-check-circle"></i> Live for everyone</span>';
        if (it.status === 'rejected') statusBadge = '<span class="badge text-bg-danger float-end"><i class="bi bi-x-circle"></i> Rejected</span>';
        foot = '<div class="card-footer bg-white border-0 d-flex gap-2"><a class="btn btn-sm btn-outline-primary flex-fill" href="item.html?id=' + encodeURIComponent(it.id) + '">View</a><button class="btn btn-sm btn-outline-danger flex-fill" data-del-new="' + it.id + '">Delete</button></div>';
      }
      col.innerHTML =
        '<div class="card item-card h-100 shadow-sm"><div class="item-thumb cat-' + it.cat.toLowerCase() + '"><i class="bi bi-' + (iconFor[it.cat] || 'box') + '"></i></div>' +
        '<div class="card-body">' + statusBadge + '<span class="badge text-bg-light border b-cat"></span> <span class="badge text-bg-light border b-cond"></span>' +
        '<h6 class="mt-2 mb-1"></h6><p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> <span class="c"></span> &nbsp;<i class="bi bi-person"></i> <span class="o"></span></p>' +
        '<div class="swap-box small"><i class="bi bi-arrow-left-right"></i> Wants: <strong class="w"></strong></div>' +
        (mode === 'mine' && it.status === 'pending' ? '<div class="small text-muted mt-2">Only you can see this until the admin approves it.</div>' : '') +
        (mode === 'mine' && it.status === 'rejected' ? '<div class="small text-danger mt-2">Admin note: <span class="rn"></span></div>' : '') +
        '</div>' + foot + '</div>';
      // textContent keeps whatever the user typed safe (no HTML injection)
      col.querySelector('.b-cat').textContent = it.cat;
      col.querySelector('.b-cond').textContent = it.cond;
      col.querySelector('h6').textContent = it.title;
      col.querySelector('.c').textContent = it.city;
      col.querySelector('.o').textContent = it.owner || 'Ali Raza';
      col.querySelector('.w').textContent = it.wants;
      var rn = col.querySelector('.rn'); if (rn) rn.textContent = it.reason || 'Not allowed on Barter Hub.';
      host.insertBefore(col, host.firstChild);
    });
  });
  $$('[data-del-new]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!window.confirm('Delete this item?')) return;
      var id = b.getAttribute('data-del-new');
      saveItems(getItems().filter(function (i) { return String(i.id) !== id; }));
      b.closest('.item-col').remove();
    });
  });

  /* 3. Browse filters (search + category + condition + city) -------- */
  var filterForm = $('#filterForm');
  if (filterForm) {
    var cols = $$('.item-col');
    var apply = function () {
      var q = ($('#fSearch').value || '').toLowerCase().trim();
      var cat = $('#fCategory').value, cond = $('#fCondition').value, city = $('#fCity').value;
      var shown = 0;
      cols.forEach(function (c) {
        var ok = (!q || c.dataset.title.toLowerCase().indexOf(q) > -1 || c.dataset.wants.toLowerCase().indexOf(q) > -1) &&
                 (!cat || c.dataset.cat === cat) && (!cond || c.dataset.cond === cond) && (!city || c.dataset.city === city);
        c.classList.toggle('d-none', !ok);
        if (ok) shown++;
      });
      $('#resultCount').textContent = shown;
      $('#noResults').classList.toggle('d-none', shown !== 0);
    };
    if (params.get('q')) $('#fSearch').value = params.get('q');
    if (params.get('cat')) $('#fCategory').value = params.get('cat');
    filterForm.addEventListener('input', apply);
    filterForm.addEventListener('change', apply);
    filterForm.addEventListener('reset', function () { setTimeout(apply, 0); });
    apply();
  }

  /* 4. Item details page: fill from data.js (or a user-added item) using ?id=... */
  var detail = $('#itemDetail');
  if (detail && window.BH_ITEMS) {
    var rawId = params.get('id') || '1';
    var item = window.BH_ITEMS.filter(function (i) { return String(i.id) === rawId; })[0];
    if (!item) {
      var mineItem = getItems().filter(function (i) { return String(i.id) === rawId; })[0];
      var isUserArea = window.location.pathname.indexOf('/user/') > -1;
      // a guest (or another user) must never see an item the admin has not approved
      if (mineItem && (mineItem.status === 'approved' || isUserArea)) {
        item = { id: mineItem.id, title: mineItem.title, cat: mineItem.cat, icon: iconFor[mineItem.cat] || 'box', cond: mineItem.cond,
                 city: mineItem.city, owner: mineItem.owner || 'Ali Raza', wantsCat: mineItem.wantsCat, wants: mineItem.wants,
                 desc: mineItem.desc || 'No description.', date: mineItem.date || 'Today' };
        if (mineItem.status !== 'approved') {
          var note = document.createElement('div');
          note.className = 'alert alert-warning';
          note.innerHTML = mineItem.status === 'rejected' ? '<i class="bi bi-x-circle"></i> The admin rejected this item. Nobody else can see it.' : '<i class="bi bi-hourglass-split"></i> Waiting for admin approval. Nobody else can see this item yet.';
          detail.insertBefore(note, detail.firstChild);
        }
      }
    }
    if (!item) {
      detail.innerHTML = '<div class="text-center py-5"><i class="bi bi-emoji-frown fs-1 text-muted"></i><h5 class="mt-2">This item is not available</h5><a class="btn btn-primary" href="browse.php">Back to browse</a></div>';
    } else {
      $$('[data-f]').forEach(function (el) {
        var key = el.getAttribute('data-f');
        if (item[key] !== undefined) el.textContent = item[key];
      });
      var thumb = $('#detailThumb');
      thumb.className = 'item-thumb lg cat-' + item.cat.toLowerCase();
      thumb.innerHTML = '<i class="bi bi-' + item.icon + '"></i>';
      document.title = item.title + ' - Barter Hub';
      $$('[data-next-link]').forEach(function (a) { a.href = a.getAttribute('data-next-link') + encodeURIComponent('item.html?id=' + item.id); });
      var reqFor = $('#requestedItemName');
      if (reqFor) reqFor.textContent = item.title;
    }
  }

  /* 5. Login / register (demo only: no real check) ------------------ */
  var loginForm = $('#loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!loginForm.checkValidity()) { loginForm.classList.add('was-validated'); return; }
      var next = params.get('next') || '';
      var safe = /^[a-z0-9\-_\/\.\?=&]+$/i.test(next) ? next : '';
      // a guest who was sent to login from an item page returns to that same item (now the user version)
      window.location.href = safe.indexOf('item.html') === 0 ? '../user/' + safe : '../user/dashboard.php';
    });
  }
  var registerForm = $('#registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var p1 = $('#regPass'), p2 = $('#regPass2');
      p2.setCustomValidity(p1.value === p2.value ? '' : 'Passwords do not match');
      if (!registerForm.checkValidity()) { registerForm.classList.add('was-validated'); return; }
      window.location.href = 'login.html?registered=1';
    });
  }
  var adminLogin = $('#adminLoginForm');
  if (adminLogin) {
    adminLogin.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!adminLogin.checkValidity()) { adminLogin.classList.add('was-validated'); return; }
      window.location.href = 'dashboard.html';
    });
  }

  /* 6. Add item: image preview + save a demo copy in the browser ---- */
  var imgInput = $('#imgInput');
  if (imgInput) {
    imgInput.addEventListener('change', function () {
      var box = $('#previewGrid'); box.innerHTML = '';
      Array.prototype.slice.call(imgInput.files, 0, 4).forEach(function (f) {
        var r = new FileReader();
        r.onload = function (ev) { var im = document.createElement('img'); im.src = ev.target.result; box.appendChild(im); };
        r.readAsDataURL(f);
      });
    });
  }
  var addForm = $('#addItemForm');
  if (addForm) {
    addForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!addForm.checkValidity()) { addForm.classList.add('was-validated'); return; }
      var newItem = {
        id: 'n' + Date.now(), status: 'pending', owner: 'Ali Raza',
        date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
        title: $('#iTitle').value.trim(), cat: $('#iCategory').value, cond: $('#iCondition').value,
        desc: $('#iDesc').value.trim(), city: $('#iLocation').value,
        wantsCat: $('#iWantCat').value, wants: $('#iWantDesc').value.trim()
      };
      var list = getItems(); list.unshift(newItem); saveItems(list);   // goes to the admin first, NOT to the public pages
      window.location.href = 'my-items.php?added=1';
    });
  }

  /* 8. Exchange requests: accept / reject / counter / complete ------ */
  function setBadge(card, text, cls) {
    var b = $('.status-badge', card);
    if (b) { b.textContent = text; b.className = 'badge status-badge ' + cls; }
  }
  $$('[data-req-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.req-card');
      if (!card) { // the counter-offer popup lives outside the card: find the card from the popup id
        var modalEl = btn.closest('.modal');
        card = $('[data-counter-modal="' + modalEl.id + '"]');
      }
      var act = btn.getAttribute('data-req-action');
      var actions = $('.req-actions', card);
      if (act === 'accept') {
        setBadge(card, 'Accepted', 'text-bg-success');
        actions.classList.add('d-none');
        $('.after-accept', card).classList.remove('d-none');
        $$('.item-status', card).forEach(function (s) { s.textContent = 'Reserved'; s.className = 'badge item-status text-bg-warning'; });
      } else if (act === 'reject') {
        setBadge(card, 'Rejected', 'text-bg-danger');
        actions.classList.add('d-none');
      } else if (act === 'complete') {
        if (!window.confirm('Confirm that the exchange is done? (In the real system both users must confirm.)')) return;
        setBadge(card, 'Completed', 'text-bg-primary');
        $('.after-accept', card).classList.add('d-none');
        $('.after-complete', card).classList.remove('d-none');
        $$('.item-status', card).forEach(function (s) { s.textContent = 'Exchanged'; s.className = 'badge item-status text-bg-secondary'; });
      } else if (act === 'counter-send') {
        setBadge(card, 'Countered', 'text-bg-info');
        actions.classList.add('d-none');
        var m = bootstrap.Modal.getInstance(btn.closest('.modal')); if (m) m.hide();
      }
    });
  });

  /* 9. Chat: switch conversation + send message --------------------- */
  $$('[data-chat-target]').forEach(function (item) {
    item.addEventListener('click', function () {
      $$('[data-chat-target]').forEach(function (i) { i.classList.remove('active'); });
      item.classList.add('active');
      $$('.chat-panel').forEach(function (p) { p.classList.add('d-none'); });
      $(item.getAttribute('data-chat-target')).classList.remove('d-none');
    });
  });
  var chatForm = $('#chatForm');
  if (chatForm) {
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = $('#chatInput'); var text = input.value.trim(); if (!text) return;
      var panel = $('.chat-panel:not(.d-none) .chat-messages');
      var b = document.createElement('div'); b.className = 'bubble me';
      b.textContent = text;
      var t = document.createElement('small'); t.textContent = 'You - just now'; b.appendChild(t);
      panel.appendChild(b); panel.scrollTop = panel.scrollHeight; input.value = '';
    });
    $$('.chat-messages').forEach(function (p) { p.scrollTop = p.scrollHeight; });
  }

  /* 10. Star rating --------------------------------------------------- */
  $$('.star-rating').forEach(function (wrap) {
    var buttons = $$('button', wrap), hidden = $('input[type=hidden]', wrap);
    buttons.forEach(function (b, idx) {
      b.addEventListener('click', function () {
        hidden.value = idx + 1;
        buttons.forEach(function (x, j) { x.classList.toggle('on', j <= idx); });
      });
    });
  });
  var rateForm = $('#rateForm');
  if (rateForm) {
    rateForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!$('#ratingValue').value) { $('#starError').classList.remove('d-none'); return; }
      $('#rateFormBox').classList.add('d-none'); $('#rateDone').classList.remove('d-none');
    });
  }

  /* 11. Modals on the item page: request + report -------------------- */
  var reqForm = $('#sendRequestForm');
  if (reqForm) {
    reqForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!reqForm.checkValidity()) { reqForm.classList.add('was-validated'); return; }
      var sent = getSent();
      sent.unshift({
        owner: ($('[data-f="owner"]') || {}).textContent || 'Other user',
        theirs: ($('#requestedItemName') || {}).textContent || 'Item',
        mine: $('select', reqForm).value, note: $('textarea', reqForm).value.trim()
      });
      try { localStorage.setItem('bh_sent_requests', JSON.stringify(sent)); } catch (err) {}
      window.location.href = 'requests.html?sent=1';
    });
  }
  var reportForm = $('#reportForm');
  if (reportForm) {
    reportForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!reportForm.checkValidity()) { reportForm.classList.add('was-validated'); return; }
      $('#reportFields').classList.add('d-none'); $('#reportThanks').classList.remove('d-none');
    });
  }

  /* 12. Profile save --------------------------------------------------- */
  var profileForm = $('#profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!profileForm.checkValidity()) { profileForm.classList.add('was-validated'); return; }
      $('#profileSaved').classList.remove('d-none');
    });
  }

  /* 13. My items: delete (demo) --------------------------------------- */
  $$('[data-delete-card]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (window.confirm('Delete this item?')) btn.closest('.col-sm-6').remove();
    });
  });

  /* 14. Admin: block / verify / remove / resolve / hide --------------- */
  $$('[data-admin-action]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('tr'), act = btn.getAttribute('data-admin-action');
      var badge = $('.row-status', row);
      var set = function (t, c) { if (badge) { badge.textContent = t; badge.className = 'badge row-status ' + c; } };
      if (act === 'block') { set('Blocked', 'text-bg-danger'); btn.textContent = 'Unblock'; btn.setAttribute('data-admin-action', 'unblock'); }
      else if (act === 'unblock') { set('Active', 'text-bg-success'); btn.textContent = 'Block'; btn.setAttribute('data-admin-action', 'block'); }
      else if (act === 'verify') { set('Verified', 'text-bg-primary'); btn.remove(); }
      else if (act === 'resolve') { set('Resolved', 'text-bg-success'); btn.remove(); }
      else if (act === 'approve') { set('Approved', 'text-bg-success'); btn.remove(); }
      else if (act === 'feature') { set('Featured', 'text-bg-warning'); btn.remove(); }
      else if (act === 'hide') { set('Hidden', 'text-bg-secondary'); btn.textContent = 'Show'; btn.setAttribute('data-admin-action', 'show'); }
      else if (act === 'show') { set('Visible', 'text-bg-success'); btn.textContent = 'Hide'; btn.setAttribute('data-admin-action', 'hide'); }
      else if (act === 'remove' || act === 'delete') {
        if (!window.confirm('Are you sure? (demo only, nothing is really deleted)')) return;
        row.style.transition = 'opacity .3s'; row.style.opacity = '0';
        setTimeout(function () { row.remove(); }, 300);
      }
    });
  });

  /* 15. Admin: add category -------------------------------------------- */
  var catForm = $('#catForm');
  if (catForm) {
    catForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = $('#catName').value.trim(); if (!name) return;
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>new</td><td class="n"></td><td>0</td><td><span class="badge row-status text-bg-success">Visible</span></td>' +
        '<td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" data-admin-action="hide">Hide</button></td>';
      tr.querySelector('.n').textContent = name;
      $('#catBody').appendChild(tr);
      var hideBtn = tr.querySelector('button');
      hideBtn.addEventListener('click', function () {
        var hidden = hideBtn.getAttribute('data-admin-action') === 'hide';
        var b = tr.querySelector('.row-status');
        b.textContent = hidden ? 'Hidden' : 'Visible'; b.className = 'badge row-status ' + (hidden ? 'text-bg-secondary' : 'text-bg-success');
        hideBtn.textContent = hidden ? 'Show' : 'Hide'; hideBtn.setAttribute('data-admin-action', hidden ? 'show' : 'hide');
      });
      catForm.reset();
    });
  }

  /* 16. Admin: quick text filter for tables ---------------------------- */
  $$('[data-table-filter]').forEach(function (input) {
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase();
      $$('#' + input.getAttribute('data-table-filter') + ' tbody tr').forEach(function (tr) {
        tr.classList.toggle('d-none', tr.textContent.toLowerCase().indexOf(q) === -1);
      });
    });
  });

  /* 17. Admin chat monitor: pick a conversation (read only) ------------ */
  $$('[data-monitor-target]').forEach(function (a) {
    a.addEventListener('click', function () {
      $$('[data-monitor-target]').forEach(function (i) { i.classList.remove('active'); });
      a.classList.add('active');
      $$('.monitor-panel').forEach(function (p) { p.classList.add('d-none'); });
      $(a.getAttribute('data-monitor-target')).classList.remove('d-none');
    });
  });

  /* 18. Requests page: show requests I sent, open the Sent tab ------- */
  var sentHost = $('#sentHost');
  if (sentHost) {
    var sentList = getSent();
    sentList.forEach(function (r) {
      var c = document.createElement('div');
      c.className = 'card mb-3 req-card';
      c.innerHTML = '<div class="card-body"><div class="d-flex justify-content-between flex-wrap gap-2 mb-3"><div><i class="bi bi-person-circle text-primary"></i> <strong class="o"></strong> <span class="text-muted small">&middot; sent just now</span></div><span class="badge status-badge text-bg-secondary">Pending</span></div>' +
        '<div class="swap-row mb-3"><div class="swap-item"><div class="small text-muted">Your item</div><div class="fw-semibold m"></div></div><div class="swap-arrow"><i class="bi bi-arrow-left-right"></i></div><div class="swap-item"><div class="small text-muted">Their item</div><div class="fw-semibold t"></div></div></div>' +
        '<div class="req-actions small text-muted"><i class="bi bi-hourglass-split"></i> Waiting for the other user to reply.</div></div>';
      c.querySelector('.o').textContent = r.owner; c.querySelector('.m').textContent = r.mine; c.querySelector('.t').textContent = r.theirs;
      sentHost.appendChild(c);
    });
    var sc = $('#sentCount'); if (sc) sc.textContent = String(1 + sentList.length);
    var sentTab = $('[data-bs-target="#tabSent"]');
    if (sentTab && params.has('sent')) new bootstrap.Tab(sentTab).show();
  }

  /* 19. Admin: review items users added (approve / reject) ----------- */
  var subBody = $('#subTable tbody');
  if (subBody) {
    var paint = function (tr, it) {
      var cls = it.status === 'approved' ? 'text-bg-success' : it.status === 'rejected' ? 'text-bg-danger' : 'text-bg-info';
      var label = it.status === 'approved' ? 'Approved - live' : it.status === 'rejected' ? 'Rejected' : 'Waiting for review';
      $('.st', tr).className = 'badge st ' + cls; $('.st', tr).textContent = label;
      $('.acts', tr).innerHTML = '';
      var mk = function (txt, cls2, newStatus) {
        var b = document.createElement('button'); b.className = 'btn btn-sm me-1 ' + cls2; b.textContent = txt;
        b.addEventListener('click', function () {
          var reason = '';
          if (newStatus === 'rejected') { reason = window.prompt('Why are you rejecting this item? (the user will see this)', 'Not allowed on Barter Hub.'); if (reason === null) return; }
          var all = getItems();
          all.forEach(function (x) { if (String(x.id) === String(it.id)) { x.status = newStatus; x.reason = reason; it.status = newStatus; } });
          saveItems(all); paint(tr, it); countPending();
        });
        $('.acts', tr).appendChild(b);
      };
      if (it.status !== 'approved') mk('Approve - show to everyone', 'btn-outline-success', 'approved');
      if (it.status !== 'rejected') mk(it.status === 'approved' ? 'Take down' : 'Reject', 'btn-outline-danger', 'rejected');
    };
    getItems().forEach(function (it) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td class="d"></td><td><strong class="t"></strong><div class="small text-muted ds"></div></td><td class="o"></td><td class="c"></td><td><span class="badge st"></span></td><td class="acts"></td>';
      $('.d', tr).textContent = it.date || 'Today'; $('.t', tr).textContent = it.title;
      $('.ds', tr).textContent = 'Wants: ' + it.wants + ' (' + it.wantsCat + ')';
      $('.o', tr).textContent = it.owner || 'Ali Raza'; $('.c', tr).textContent = it.cat;
      paint(tr, it); subBody.insertBefore(tr, subBody.firstChild);
    });
  }
  function countPending() {
    var n = getItems().filter(function (i) { return i.status === 'pending'; }).length;
    $$('[data-pending-count]').forEach(function (el) { el.textContent = String(n + 3); });
  }
  countPending();
})();
