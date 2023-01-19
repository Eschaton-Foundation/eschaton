/*
	CHIPS_KEYBOARD_NAV v0.001
	Last update 9/30/2021
*/
// import app from "./project.js";
const CHIPS_KEYBOARD_NAV = {
	constructor() {},
	init() {
		let appScope = this;
		this.modalopen = false;

		// rebind
		this.jsEvts = this.bindEvents();
	},
	bindEvents() {
		let appScope = this;

		$(document).keyup(function (e) {
			// console.log(e);
			if (e.key === "Escape" || e.keyCode === 27) {
				// appScope.closeModal();
				// app.closeModal();
				if ($("body").hasClass("menu-on")) {
					$("body").removeClass("menu-on");
				} else if ($("body").hasClass("modal-on")) {
					$("body").removeClass("modal-on");
				}
				$("body").removeClass("menu-on-lock");
			}
		});

		$(document).on("focusin", function (event) {
			var $target = $(event.target);

			if (
				$(".modal").hasClass("modal") &&
				$("body").hasClass("modal-on")
			) {
				if (!$target.closest(".modal").length) {
					console.log("You focused outside of modal contents!");
					$(".modal-close").focus();
				}
			} else if ($("body").hasClass("menu-on")) {
				if (
					!$target.closest("header").length &&
					!$target.closest("nav").length
				) {
					console.log("You focused outside of nav!");
					$(".site-name").focus();
				}
			} else if (appScope.modalopen) {
				// Keep focus inside of menu
				if (!$target.closest("header").length) {
					console.log("You focused outside of nav!");
					// $(".menu-close-toggle").focus();
				}
			} else if (appScope.searchopen) {
				if (!$target.closest("header").length) {
					console.log("You focused outside of nav!");
					// $(".menu-search-toggle").focus();
				}
			}
		});
	},
};

export default CHIPS_KEYBOARD_NAV;
