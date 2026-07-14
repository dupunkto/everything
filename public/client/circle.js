// Sizes the hand-drawn circle in a [z-circle] field around its input's text.

const gauge = document.createElement("canvas").getContext("2d");

const fit = (field) => {
  const input = field.querySelector("input");
  const ring = field.querySelector(".circle");
  if(!input || !ring) return;

  const style = getComputedStyle(input);
  gauge.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
  const width = gauge.measureText(input.value || input.placeholder || "").width;
  const origin = parseFloat(style.paddingLeft) + parseFloat(style.borderLeftWidth);

  // The ellipse tapers to points at its ends, so wide text needs
  // proportionally more horizontal room to stay inside the ring; the
  // vertical margin is fixed.
  const pad_x = Math.max(18, width * 0.16), pad_y = 7;
  ring.style.left = `${origin - pad_x}px`;
  ring.style.width = `${width + pad_x * 2}px`;
  ring.style.top = `${-pad_y}px`;
  ring.style.height = `${input.offsetHeight + pad_y * 2}px`;
};

zhtml.directive("z-circle", (field) => {
  field.addEventListener("input", () => fit(field));
  fit(field);
});
