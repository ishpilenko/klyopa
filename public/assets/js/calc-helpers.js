/**
 * Shared helpers for all interactive calculators.
 * Loaded on every calculator page via base_tool.html.twig.
 */
(function (root) {
  'use strict';

  var CalcHelpers = {

    /**
     * Format a number as USD currency.
     * formatCurrency(1234.5)           → "$1,234.50"
     * formatCurrency(0.00000823, 'USD', 8) → "$0.00000823"
     */
    formatCurrency: function (value, currency, maxDecimals) {
      currency = currency || 'USD';
      if (value === null || value === undefined || !isFinite(value)) return '—';
      var abs = Math.abs(value);
      var decimals = (maxDecimals !== undefined) ? maxDecimals : (abs < 0.01 ? 8 : 2);
      try {
        return new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: currency,
          minimumFractionDigits: 2,
          maximumFractionDigits: decimals
        }).format(value);
      } catch (e) {
        return '$' + value.toFixed(decimals);
      }
    },

    /**
     * Format a crypto amount with adaptive decimals.
     * formatCrypto(0.00045678)  → "0.00045678"
     * formatCrypto(1234.5, 2)   → "1,234.50"
     */
    formatCrypto: function (value, decimals) {
      if (value === null || value === undefined || !isFinite(value)) return '—';
      decimals = (decimals !== undefined) ? decimals : (Math.abs(value) < 0.01 ? 8 : 4);
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: decimals
      }).format(value);
    },

    /**
     * Format as percentage with sign.
     * formatPercent(12.5)   → "+12.50%"
     * formatPercent(-3.14)  → "-3.14%"
     */
    formatPercent: function (value, decimals) {
      if (value === null || value === undefined || !isFinite(value)) return '—';
      decimals = (decimals !== undefined) ? decimals : 2;
      var sign = value >= 0 ? '+' : '';
      return sign + value.toFixed(decimals) + '%';
    },

    /**
     * Format a large number with K/M/B suffix.
     * formatCompact(1234567) → "1.23M"
     */
    formatCompact: function (value) {
      if (value === null || value === undefined || !isFinite(value)) return '—';
      var abs = Math.abs(value);
      if (abs >= 1e9) return (value / 1e9).toFixed(2) + 'B';
      if (abs >= 1e6) return (value / 1e6).toFixed(2) + 'M';
      if (abs >= 1e3) return (value / 1e3).toFixed(2) + 'K';
      return value.toFixed(2);
    },

    /**
     * Apply profit/loss colour class to an element.
     */
    colorValue: function (el, value) {
      el.classList.remove('value-positive', 'value-negative', 'value-neutral');
      if (value > 0) el.classList.add('value-positive');
      else if (value < 0) el.classList.add('value-negative');
      else el.classList.add('value-neutral');
    },

    /**
     * Simple debounce.
     */
    debounce: function (fn, ms) {
      var t;
      return function () {
        clearTimeout(t);
        t = setTimeout(fn, ms || 400);
      };
    },

    /**
     * Show spinner / hide spinner on a button.
     */
    setLoading: function (btn, loading) {
      if (!btn) return;
      if (loading) {
        btn.dataset.originalText = btn.textContent;
        btn.textContent = 'Calculating…';
        btn.disabled = true;
      } else {
        btn.textContent = btn.dataset.originalText || 'Calculate';
        btn.disabled = false;
      }
    },
  };

  root.CalcHelpers = CalcHelpers;

}(window));
