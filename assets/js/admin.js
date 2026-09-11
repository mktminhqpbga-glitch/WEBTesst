(function () {
  'use strict';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content;
  var uploadUrl = (document.querySelector('meta[name="upload-url"]') || {}).content;

  /* Thêm dòng biến thể */
  var addBtn = document.querySelector('[data-add-variant]');
  if (addBtn) {
    var n = 1000;
    addBtn.addEventListener('click', function () {
      var tpl = document.getElementById('vtpl').innerHTML.replace(/\[999\]/g, '[' + (n++) + ']');
      document.getElementById('vrows').insertAdjacentHTML('beforeend', tpl);
    });
  }
  /* Ô chọn màu đồng bộ với ô mã màu */
  document.addEventListener('input', function (e) {
    if (e.target.matches('[data-swatch]')) e.target.nextElementSibling.value = e.target.value.toUpperCase();
  });

  /* Trình soạn thảo Quill */
  if (window.Quill) {
    document.querySelectorAll('textarea[data-editor]').forEach(function (ta) {
      var holder = document.createElement('div');
      holder.innerHTML = ta.value;
      ta.style.display = 'none';
      ta.parentNode.insertBefore(holder, ta.nextSibling);
      var q = new Quill(holder, {
        theme: 'snow',
        modules: { toolbar: {
          container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link', 'image', 'video'], ['clean']],
          handlers: { image: function () { pickImage(q); } }
        } }
      });
      var form = ta.closest('form');
      form.addEventListener('submit', function () { ta.value = q.root.innerHTML === '<p><br></p>' ? '' : q.root.innerHTML; });
    });
  }
  function pickImage(q) {
    var inp = document.createElement('input');
    inp.type = 'file'; inp.accept = 'image/*';
    inp.onchange = function () {
      if (!inp.files[0]) return;
      var fd = new FormData(); fd.append('file', inp.files[0]); fd.append('_csrf', csrf);
      fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.url) { var range = q.getSelection(true); q.insertEmbed(range.index, 'image', d.url); }
          else alert(d.error || 'Tải ảnh lỗi.');
        })
        .catch(function () { alert('Tải ảnh lỗi. Kiểm tra kết nối.'); });
    };
    inp.click();
  }
})();
