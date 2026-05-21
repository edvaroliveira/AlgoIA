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
    var saveUrl = ta.dataset.saveUrl;
    var answer = ta.value.trim();

    if (!answer || !attemptId || !saveUrl) return;

    var body = new URLSearchParams({
      _csrf_token: csrf,
      question_id: questionId,
      answer: ta.value,
    });

    fetch(saveUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    })
      .then(function (r) {
        if (!r.ok) {
          throw new Error("autosave_failed");
        }

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

  // ── Draft activation form validation ─────────────────────────────────────
  var activationForm = document.querySelector("[data-activation-form]");

  if (activationForm) {
    var feedbackEl = document.querySelector("[data-activation-feedback]");

    activationForm.addEventListener("submit", function (event) {
      var errors = [];
      var questionCount = Number(activationForm.dataset.questionCount || "0");
      var selectedTurmas = activationForm.querySelectorAll(
        'input[name="turma_ids[]"]:checked',
      );

      if (questionCount < 1) {
        errors.push(
          "Adicione pelo menos uma questão antes de ativar o exercício.",
        );
      }

      if (!selectedTurmas.length) {
        errors.push("Selecione pelo menos uma turma para ativação.");
      }

      if (!feedbackEl) {
        return;
      }

      if (errors.length) {
        event.preventDefault();
        feedbackEl.innerHTML = errors
          .map(function (message) {
            return "<div>" + message + "</div>";
          })
          .join("");
        feedbackEl.classList.remove("is-hidden");
        feedbackEl.scrollIntoView({ behavior: "smooth", block: "nearest" });
        return;
      }

      feedbackEl.innerHTML = "";
      feedbackEl.classList.add("is-hidden");
    });

    activationForm
      .querySelectorAll('input[name="turma_ids[]"]')
      .forEach(function (input) {
        input.addEventListener("change", function () {
          if (!feedbackEl) {
            return;
          }

          feedbackEl.innerHTML = "";
          feedbackEl.classList.add("is-hidden");
        });
      });
  }
})();
