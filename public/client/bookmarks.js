const fallback = (img) =>
  img.replaceWith(Object.assign(document.createElement("i"),
    { className: "bookmark__favicon fa-regular fa-globe" }));

const sweep = () => {
  for(const img of document.querySelectorAll("img.bookmark__favicon"))
    if(img.complete && img.naturalWidth == 0) fallback(img);
};

document.addEventListener("error", (e) => {
  if(e.target.matches?.("img.bookmark__favicon")) fallback(e.target);
}, true);

document.addEventListener("x-swap", sweep);
sweep();
