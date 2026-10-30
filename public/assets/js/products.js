"use strict";

document.querySelector("#form-errors")?.focus();

const productForm = document.querySelector("#product-form");
if (productForm) {
  const rows = document.querySelector("#variant-rows");
  const add = document.querySelector("#add-variant");
  const stock = document.querySelector("#stock");
  const total = document.querySelector("#variant-total");
  let nextIndex = rows.children.length;
  const refresh = () => {
    const hasVariants = rows.children.length > 0;
    stock.readOnly = hasVariants;
    document.querySelector("#no-variants").hidden = hasVariants;
    add.disabled = rows.children.length >= 100;
    const quantities = [...rows.querySelectorAll('[data-field="stock"]')];
    const sum = quantities.reduce((value, input) => value + (Number(input.value) || 0), 0);
    total.textContent = hasVariants ? `Estoque disponível: ${sum.toLocaleString("pt-BR")} un. · ${rows.children.length}/100 variações` : "";
    if (hasVariants) stock.value = "0";
  };
  add.addEventListener("click", () => {
    if (rows.children.length >= 100) return;
    const fragment = document.querySelector("#variant-template").content.cloneNode(true);
    const row = fragment.querySelector(".variant-row");
    const index = nextIndex++;
    row.querySelectorAll("[data-field]").forEach(input => {
      const field = input.dataset.field;
      input.name = `variants[${index}][${field}]`;
      input.id = `variant-${field}-${index}`;
      const label = row.querySelector(`[data-label="${field}"]`);
      if (label) label.htmlFor = input.id;
    });
    rows.append(fragment);
    row.querySelector('[data-field="name"]').focus();
    refresh();
  });
  rows.addEventListener("click", event => {
    const button = event.target.closest(".remove-variant");
    if (!button) return;
    button.closest(".variant-row").remove();
    refresh();
    add.focus();
  });
  rows.addEventListener("input", refresh);
  refresh();
}

document.querySelector("#delete-product")?.addEventListener("show.bs.modal", event => {
  const trigger = event.relatedTarget;
  document.querySelector("#delete-product-id").value = trigger?.dataset.productId ?? "";
  document.querySelector("#delete-product-name").textContent = trigger?.dataset.productName ?? "";
});
