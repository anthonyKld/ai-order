"use strict";

// Após navegar até o conteúdo pelo teclado, o foco acompanha o link de atalho.
document.querySelector(".skip-link")?.addEventListener("click", () => {
  document.querySelector("#main")?.focus({ preventScroll: true });
});
