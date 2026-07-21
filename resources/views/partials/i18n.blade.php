function applyI18n(){
  var dict = i18n[currentLang];
  document.querySelectorAll("[data-i18n]").forEach(function(el){
    var key = el.getAttribute("data-i18n");
    if(dict[key] !== undefined){ el.textContent = dict[key]; }
  });
  if (typeof onLangChange === "function") { onLangChange(); }
}

function setLang(lang){
  currentLang = lang;
  var app = document.getElementById("app");
  app.setAttribute("lang", lang);
  app.setAttribute("dir", lang === "ar" ? "rtl" : "ltr");
  document.getElementById("btnEn").classList.toggle("is-active", lang === "en");
  document.getElementById("btnAr").classList.toggle("is-active", lang === "ar");
  applyI18n();
}

document.getElementById("btnEn").addEventListener("click", function(){ setLang("en"); });
document.getElementById("btnAr").addEventListener("click", function(){ setLang("ar"); });
