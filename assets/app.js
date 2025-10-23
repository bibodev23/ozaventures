import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';



  document.addEventListener("DOMContentLoaded", function () {
console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
    const menuMobile = document.querySelector(".toggle")
    const nav = document.querySelector("nav")
    menuMobile.addEventListener("click", () => {
        nav.classList.toggle("active")
    })
});