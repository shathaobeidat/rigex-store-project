"use strict";

(function () {
  const form = document.getElementById("pcBuilderForm");

  if (!form) {
    return;
  }

  // ======================================================
  // ELEMENTS
  // ======================================================

  const panels = document.querySelectorAll("[data-step-panel]");
  const indicators = document.querySelectorAll("[data-step-indicator]");

  const nextButton = document.getElementById("pbxNextStep");
  const backButton = document.getElementById("pbxBackStep");

  const purposeInputs = document.querySelectorAll('input[name="purpose"]');

  const resolutionInputs = document.querySelectorAll(
    'input[name="resolution"]',
  );

  const resolutionGroup = document.getElementById("pbResolutionGroup");

  const budgetInput = document.getElementById("pbBudget");

  const summaryPurpose = document.getElementById("pbxSummaryPurpose");

  const summaryBudget = document.getElementById("pbxSummaryBudget");

  const summaryResolution = document.getElementById("pbxSummaryResolution");

  const summaryResolutionRow = document.getElementById(
    "pbxSummaryResolutionRow",
  );

  const submitButton = document.getElementById("pbGenerateButton");

  const loadingOverlay = document.getElementById("pbxLoading");
  const loadingTitle = document.getElementById("pbxLoadingTitle");
  const loadingMessage = document.getElementById("pbxLoadingMessage");

  const loadingSteps = document.querySelectorAll("[data-loading-step]");

  // ======================================================
  // STATE
  // ======================================================

  let currentStep = 1;

  const purposeLabels = {
    gaming: "Gaming",
    workstation: "Workstation",
    general_use: "Everyday use",
  };

  const loadingMessages = [
    {
      title: "Let’s find the right components.",
      message:
        "We’re checking the products currently available in REGIX inventory.",
    },
    {
      title: "Making sure the parts work together.",
      message:
        "We’re reviewing the known compatibility checks for this combination.",
    },
    {
      title: "Finding the right performance balance.",
      message:
        "We’re comparing the available benchmark data for your selected purpose.",
    },
    {
      title: "Keeping your budget in focus.",
      message:
        "We’re preparing the recommended configuration and alternative builds.",
    },
  ];

  // ======================================================
  // HELPERS
  // ======================================================

  function getSelectedPurpose() {
    const selected = document.querySelector('input[name="purpose"]:checked');

    return selected ? selected.value : "gaming";
  }

  function getSelectedResolution() {
    const selected = document.querySelector('input[name="resolution"]:checked');

    return selected ? selected.value : "1440p";
  }

  function updatePurposeCards() {
    purposeInputs.forEach(function (input) {
      const card = input.closest(".pbx-purpose-card");

      if (!card) {
        return;
      }

      card.classList.toggle("is-selected", input.checked);
    });
  }

  function updateResolutionCards() {
    resolutionInputs.forEach(function (input) {
      const card = input.closest("label");

      if (!card) {
        return;
      }

      card.classList.toggle("is-selected", input.checked);
    });
  }

  // ======================================================
  // STEP NAVIGATION
  // ======================================================

  function showStep(step) {
    currentStep = step;

    panels.forEach(function (panel) {
      const panelStep = Number(panel.dataset.stepPanel);

      panel.hidden = panelStep !== step;
    });

    indicators.forEach(function (indicator) {
      const indicatorStep = Number(indicator.dataset.stepIndicator);

      indicator.classList.toggle("is-active", indicatorStep === step);

      indicator.classList.toggle("is-complete", indicatorStep < step);

      if (indicatorStep === step) {
        indicator.setAttribute("aria-current", "step");
      } else {
        indicator.removeAttribute("aria-current");
      }
    });

    const activePanel = document.querySelector(`[data-step-panel="${step}"]`);

    if (activePanel) {
      const heading = activePanel.querySelector("h3");

      if (heading) {
        heading.setAttribute("tabindex", "-1");
        heading.focus({ preventScroll: true });
      }
    }
  }

  // ======================================================
  // SUMMARY
  // ======================================================

  function updateSummary() {
    const purpose = getSelectedPurpose();
    const resolution = getSelectedResolution();

    if (summaryPurpose) {
      summaryPurpose.textContent = purposeLabels[purpose] || "Custom PC";
    }

    if (summaryBudget && budgetInput) {
      const value = budgetInput.value.trim();

      summaryBudget.textContent = value ? `${value} JOD` : "Not set";
    }

    const isGaming = purpose === "gaming";

    if (resolutionGroup) {
      resolutionGroup.hidden = !isGaming;
    }

    if (summaryResolutionRow) {
      summaryResolutionRow.hidden = !isGaming;
    }

    if (summaryResolution) {
      summaryResolution.textContent = resolution.toUpperCase();
    }

    updatePurposeCards();
    updateResolutionCards();
  }

  // ======================================================
  // EVENT LISTENERS
  // ======================================================

  purposeInputs.forEach(function (input) {
    input.addEventListener("change", updateSummary);
  });

  resolutionInputs.forEach(function (input) {
    input.addEventListener("change", updateSummary);
  });

  if (budgetInput) {
    budgetInput.addEventListener("input", updateSummary);
  }

  if (nextButton) {
    nextButton.addEventListener("click", function () {
      const selectedPurpose = document.querySelector(
        'input[name="purpose"]:checked',
      );

      if (!selectedPurpose) {
        return;
      }

      updateSummary();
      showStep(2);
    });
  }

  if (backButton) {
    backButton.addEventListener("click", function () {
      showStep(1);
    });
  }

  // ======================================================
  // BUILDING EXPERIENCE
  // ======================================================

  function updateLoadingStep(step) {
    loadingSteps.forEach(function (item) {
      const itemStep = Number(item.dataset.loadingStep);

      item.classList.toggle("is-current", itemStep === step);

      item.classList.toggle("is-done", itemStep < step);
    });

    const message = loadingMessages[step];

    if (message) {
      loadingTitle.textContent = message.title;
      loadingMessage.textContent = message.message;
    }
  }

  function startBuildingExperience() {
    if (!loadingOverlay) {
      return;
    }

    loadingOverlay.hidden = false;

    document.body.style.overflow = "hidden";

    updateLoadingStep(0);

    let currentLoadingStep = 0;

    const loadingTimer = window.setInterval(function () {
      if (currentLoadingStep < loadingMessages.length - 1) {
        currentLoadingStep++;
        updateLoadingStep(currentLoadingStep);
      } else {
        window.clearInterval(loadingTimer);
      }
    }, 1600);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute("aria-disabled", "true");
    }
  }

  form.addEventListener("submit", function (event) {
    // Do not submit directly from step one.
    if (currentStep !== 2) {
      event.preventDefault();
      showStep(2);
      return;
    }

    // Browser validation remains enabled.
    if (!form.reportValidity()) {
      event.preventDefault();
      return;
    }

    startBuildingExperience();
  });

  // ======================================================
  // INITIAL STATE
  // ======================================================

  updateSummary();
  showStep(1);

  // Scroll to generated results after the server response.
  const results = document.getElementById("build-results");

  if (results && window.location.hash === "#build-results") {
    window.requestAnimationFrame(function () {
      results.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    });
  }
})();
