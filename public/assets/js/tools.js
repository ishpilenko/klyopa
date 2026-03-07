/**
 * Tool Calculator Engine — Vanilla JS
 * Reads config from <script id="tool-config" type="application/json">
 * and drives the calculator form.
 */
(function () {
  'use strict';

  const configEl = document.getElementById('tool-config');
  if (!configEl) return;

  let config;
  try {
    config = JSON.parse(configEl.textContent);
  } catch (e) {
    console.error('Invalid tool config JSON', e);
    return;
  }

  const form = document.getElementById('tool-form');
  const btnCalculate = document.getElementById('btn-calculate');
  const resultsPanel = document.getElementById('tool-results');

  if (!form || !btnCalculate) return;

  // ── Formatters ─────────────────────────────────────────────
  const fmt = {
    currency: (v, out) => {
      const n = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(v));
      const sign = v < 0 ? '−' : '';
      return sign + (out.prefix || '') + n + (out.suffix || '');
    },
    percent: (v, out) => {
      const n = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
      return (v > 0 ? '+' : '') + n + (out.suffix || '%');
    },
    number: (v, out) => {
      const digits = out.decimals ?? 4;
      const n = new Intl.NumberFormat('en-US', { minimumFractionDigits: 0, maximumFractionDigits: digits }).format(v);
      return (out.prefix || '') + n + (out.suffix || '');
    },
    integer: (v, out) => {
      return (out.prefix || '') + new Intl.NumberFormat('en-US').format(Math.round(v)) + (out.suffix || '');
    },
    ratio: (v) => `${v.toFixed(2)} : 1`,
  };

  function formatValue(value, output) {
    if (value === null || value === undefined || !isFinite(value) || isNaN(value)) return '—';
    const formatter = fmt[output.format] || fmt.number;
    return formatter(value, output);
  }

  // ── Formula evaluator ───────────────────────────────────────
  function evaluate(formula, vars) {
    try {
      // Allowlist: only safe Math functions accessible
      const keys = Object.keys(vars);
      const values = Object.values(vars);
      // eslint-disable-next-line no-new-func
      const fn = new Function(...keys, 'Math', `'use strict'; return (${formula});`);
      const result = fn(...values, Math);
      return isFinite(result) ? result : null;
    } catch (e) {
      return null;
    }
  }

  // ── Read current field values ───────────────────────────────
  function readFields() {
    const fields = config.fields || [];
    const vars = {};
    for (const field of fields) {
      const el = form.querySelector(`[data-field="${field.name}"]`);
      if (!el) continue;
      if (field.type === 'select') {
        vars[field.name] = parseFloat(el.value) || el.value;
      } else {
        vars[field.name] = parseFloat(el.value) || 0;
      }
    }
    return vars;
  }

  // ── Run calculation ─────────────────────────────────────────
  function calculate() {
    const vars = readFields();
    const outputs = config.outputs || [];
    let hasError = false;

    for (const output of outputs) {
      const el = document.getElementById(`result-${output.name}`);
      if (!el || !output.formula) continue;

      const value = evaluate(output.formula, vars);
      const formatted = formatValue(value, output);

      el.textContent = formatted;

      // Color-code profit/loss for currency results
      if (output.format === 'currency' || output.format === 'percent') {
        if (value !== null && value < 0) {
          el.style.color = 'var(--color-error, #dc2626)';
        } else if (value !== null && value > 0) {
          el.style.color = 'var(--color-success, #16a34a)';
        } else {
          el.style.color = '';
        }
      }

      if (value === null) hasError = true;
    }

    if (resultsPanel) {
      resultsPanel.hidden = false;
      resultsPanel.classList.add('results--visible');
    }
  }

  // ── Odds Converter special handling ────────────────────────
  function runOddsConverter() {
    const formatEl = form.querySelector('[data-field="format"]');
    const valueEl = form.querySelector('[data-field="value"]');
    if (!formatEl || !valueEl) return;

    const format = formatEl.value;
    const raw = valueEl.value.trim();

    let decimal = null;

    try {
      if (format === 'decimal') {
        decimal = parseFloat(raw);
      } else if (format === 'fractional') {
        // e.g. "3/2" or "3-2"
        const parts = raw.split(/[\/\-]/);
        if (parts.length === 2) {
          decimal = parseFloat(parts[0]) / parseFloat(parts[1]) + 1;
        }
      } else if (format === 'american') {
        const v = parseFloat(raw);
        if (v > 0) decimal = v / 100 + 1;
        else if (v < 0) decimal = 100 / Math.abs(v) + 1;
      }
    } catch (e) {
      decimal = null;
    }

    if (!decimal || decimal <= 1) {
      setResult('decimal', '—');
      setResult('fractional', '—');
      setResult('american', '—');
      setResult('implied_prob', '—');
      setResult('payout', '—');
      return;
    }

    // Decimal
    setResult('decimal', decimal.toFixed(3));

    // Fractional: numerator/denominator
    const numerator = decimal - 1;
    const denom = 1;
    const gcd = findGCD(Math.round(numerator * 100), 100);
    setResult('fractional', `${Math.round(numerator * 100 / gcd)}/${100 / gcd}`);

    // American
    let american;
    if (decimal >= 2) american = '+' + Math.round((decimal - 1) * 100);
    else american = '-' + Math.round(100 / (decimal - 1));
    setResult('american', american);

    // Implied probability
    const prob = (1 / decimal) * 100;
    setResult('implied_prob', prob.toFixed(2) + '%');

    // Profit on $100
    setResult('payout', '$' + ((decimal - 1) * 100).toFixed(2));

    if (resultsPanel) {
      resultsPanel.hidden = false;
      resultsPanel.classList.add('results--visible');
    }
  }

  function setResult(name, value) {
    const el = document.getElementById(`result-${name}`);
    if (el) el.textContent = value;
  }

  function findGCD(a, b) {
    return b === 0 ? a : findGCD(b, a % b);
  }

  // ── Detect tool type and wire up ────────────────────────────
  const toolType = config.tool_type || 'calculator';

  function runTool() {
    if (toolType === 'converter' && config.input_fields) {
      runOddsConverter();
    } else {
      calculate();
    }
  }

  // Calculate on button click
  btnCalculate.addEventListener('click', runTool);

  // Also calculate on Enter key in any input
  form.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runTool();
    }
  });

  // Auto-calculate on input change (debounced)
  let debounceTimer;
  form.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runTool, 400);
  });
  form.addEventListener('change', runTool);

  // Run on page load with default values
  runTool();
})();
