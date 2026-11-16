// 検索対象のプルダウンを、検索窓の横へ移す（見た目だけの補助。検索入力欄の左側）。
// 検索窓が見つからない・移せない場合は何もせず、フィルター欄のまま使える。
(function () {
  'use strict';
  try {
    var wrap = document.getElementById('lc-search-in-wrap');
    var box = document.querySelector('p.search-box');
    if (!wrap || !box) return;
    // 検索入力欄の左へ置く。入力欄が見つからなければ、検索ボタンの前、それも無ければ末尾。
    var input = box.querySelector('input[type="search"], input[name="s"]');
    var submit = box.querySelector('input[type="submit"], button[type="submit"]');
    box.insertBefore(wrap, input || submit || null);
    wrap.classList.add('is-moved');
  } catch (e) { /* 失敗しても機能は変わらない */ }
})();
