/**
 * IAProg — app.js
 * Auto-save answers while student types (debounced, AJAX POST).
 */
(function () {
  "use strict";

  // ── Auto-save ──────────────────────────────────────────────────────────────
  const textareas = document.querySelectorAll("textarea[data-question]");

  textareas.forEach(function (ta) {
    var timer = null;
    var statusEl = document.getElementById("status-" + ta.dataset.question);

    ta.addEventListener("input", function () {
      clearTimeout(timer);
      if (statusEl) statusEl.textContent = "Aguardando...";

      timer = setTimeout(function () {
        saveAnswer(ta, statusEl);
      }, 1200);
    });
  });

  function saveAnswer(ta, statusEl) {
    var attemptId = ta.dataset.attempt;
    var questionId = ta.dataset.question;
    var csrf = ta.dataset.csrf;
    var answer = ta.value.trim();

    if (!answer || !attemptId) return;

    var body = new URLSearchParams({
      _csrf_token: csrf,
      question_id: questionId,
      answer: ta.value,
    });

    fetch("/student/attempts/" + attemptId + "/answer", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (statusEl) {
          statusEl.textContent = data.ok
            ? "✓ Salvo automaticamente"
            : "Erro ao salvar";
        }
      })
      .catch(function () {
        if (statusEl) statusEl.textContent = "Sem conexão – rascunho não salvo";
      });
  }

  // ── Uppercase turma key fields ─────────────────────────────────────────────
  document.querySelectorAll(".form-input--key").forEach(function (el) {
    el.addEventListener("input", function () {
      var pos = el.selectionStart;
      el.value = el.value.toUpperCase();
      el.setSelectionRange(pos, pos);
    });
  });

  // ── Auto-dismiss alerts after 6 s ─────────────────────────────────────────
  document.querySelectorAll(".alert--success").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity .4s";
      el.style.opacity = "0";
      setTimeout(function () {
        el.remove();
      }, 400);
    }, 6000);
  });
})();
