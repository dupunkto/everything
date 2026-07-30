var str_normalize = window.str_normalize || ((str) =>
  str.toLowerCase().normalize("NFD").replace(/\p{Mn}/gu, ""));
