import CHIPS_KEYBOARD_NAV from "./_chips-keyboardnav.js";
import CHIPSSLIDER from "./components/_chips-slider.js";
// import barba from "@barba/core";
// import barbaCss from "@barba/css";
// barba.use(barbaCss);
// barba.init();
// Barba.Dispatcher.on(
// 	"newPageReady",
// 	function (newStatus, oldStatus, container, html) {
// 		const navs = $(html).find(".menu-primary-nav-container > li"); // New ones
// 		$(".menu-primary-nav-container > li").each((i, el) =>
// 			$(el).html($(navs[i]).html())
// 		); // Replace each old ones
// 	}
// );
class App {
	constructor(options) {
		this.defaults = {};
		this.options = {};
		Object.assign(this.options, this.defaults, options);
		this.sliders = [];

		this.namespace = "App";
		this.init();
	}

	init() {
		/* DO something on load */
		$(".slideshow").each(function () {
			this.slider = new CHIPSSLIDER({ targetEl: $(this) });
		});

		// this.initMenu = new CHIPSMENU();

		this.zoomDOM = null;
		this.kbNav = CHIPS_KEYBOARD_NAV.init();
		this.initZoom = () => {
			var element = document.querySelector("#zoomModal");
			this.zoomDOM = panzoom(element, {
				maxZoom: 2.5,
				minZoom: 1,
				onTouch: function (e) {
					return false;
				},
			});
		};

		this.closeModal = () => {
			$("body").removeClass("modal-on");
			console.log("closing modal");
			if (this.zoomDOM) {
				this.zoomDOM.dispose();
			}
		};
	}
}

const app = new App();

$(".menu-toggle").on("click", function () {
	if ($("body").hasClass("menu-on-lock")) {
		$("body").removeClass("menu-on-lock");
		$("body").removeClass("menu-on");
	} else {
		$("body").addClass("menu-on-lock");
	}
});

var mouseTimer = null;
$(".menu-toggle").mouseenter(function () {
	clearTimeout(mouseTimer);
	$("body").addClass("allow-animation menu-on");
});
$("header,.menu-primary-nav-container").mouseenter(function () {
	if ($("body").hasClass("menu-on")) {
		clearTimeout(mouseTimer);
		$("body").addClass("menu-on");
	}
});
$("header,.menu-primary-nav-container").mouseleave(function () {
	mouseTimer = setTimeout(function () {
		$("body").removeClass("menu-on");
	}, 10);
});

$(".trp-language-switcher").mouseenter(function () {
	$("body").addClass("translate-on");
});
$(".trp-language-switcher").mouseleave(function () {
	$("body").removeClass("translate-on");
});
// $("header,.menu-primary-nav-container").mouseenter(function () {
// 	clearTimeout(mouseTimer);
// 	$("body").addClass("allow-animation menu-on");
// });
// $("header,.menu-primary-nav-container").mouseleave(function () {
// 	//mouseTimer = setTimeout(function () {
// 	$("body").removeClass("menu-on");
// 	//}, 100);
// });

$(".img-enlarge").on("click", function (e) {
	e.preventDefault();
	var targetURL = $(this).attr("href");

	$(".modal-content-scroll").html(
		'<img src="' + targetURL + '" class="modal-img" border="0" />'
	);
	$("body").addClass("modal-on");
	$(".modal").addClass("modal-interact");
	app.initZoom();
});
$(".modal-close").on("click", app.closeModal);

$("textarea").each(function () {
	$(this).addClass("empty");
	$(this).on("change", function () {
		if ($(this).val() !== "") {
			$(this).removeClass("empty");
		} else {
			$(this).addClass("empty");
		}
	});
});

let scrollPosOld = 0;
let scrollPosNew = 0;
let scrollDelta = 0;
let scrollThreshold = 20;
let scrollDown = false;
window.addEventListener("scroll", (e) => {
	scrollPosNew = window.pageYOffset;
	let distanceTraveled = scrollDelta - scrollPosNew;
	if (window.pageYOffset > 5) {
		if (distanceTraveled <= -1 * scrollThreshold) {
			// console.log("Down");
			scrollDelta = scrollPosNew;
			scrollDown = true;
			$("header").addClass("scrolled");
		} else if (distanceTraveled >= scrollThreshold) {
			// console.log("Up");
			scrollDelta = scrollPosNew;
			scrollDown = false;
			$("header").removeClass("scrolled");
		}
	} else {
		scrollDelta = scrollPosNew;
		scrollDown = false;
		$("header").removeClass("scrolled");
	}
	// console.log(distanceTraveled);
	// if (scrollPosOld < scrollPosNew) {
	// 	console.log("Up");
	// } else if (scrollPosOld > scrollPosNew) {
	// 	console.log("Down");
	// }
	scrollPosOld = scrollPosNew;
});

if (localStorage.getItem("eschaton_consent") === null) {
	$(".consent-wrap").addClass("consent-on");
	$(".consent-bg").addClass("consent-on");
} else {
	let localVar = localStorage.getItem("eschaton_consent");
	const item = JSON.parse(localVar);
	const now = new Date();
	if (now.getTime() > item.expiry) {
		localStorage.removeItem("eschaton_consent");
		$(".consent-wrap").addClass("consent-on");
		$(".consent-bg").addClass("consent-on");
	}
}
$(".consent-optin").on("click", function (e) {
	e.preventDefault();
	const now = new Date();
	const ttl = 360000;
	// set ttl to 1 day

	// const ttl = 10000;
	const item = {
		value: "Agreed",
		expiry: now.getTime() + ttl,
	};
	localStorage.setItem("eschaton_consent", JSON.stringify(item));
	$(".consent-wrap").addClass("consent-added");
	$(".consent-bg").addClass("consent-added");
});
$(".consent-optin-vis").on("click", function (e) {
	e.preventDefault();
	const now = new Date();
	const ttl = 360000;
	// const ttl = 10000;
	const item = {
		value: "Agreed",
		expiry: now.getTime() + ttl,
	};
	localStorage.setItem("eschaton_consent", JSON.stringify(item));
	$(".consent-wrap").addClass("consent-added");
	$(".consent-bg").addClass("consent-added");
});
$(".ginput_quantity").each(function () {
	$(this)
		.parent()
		.append(
			'<button class="qty-change decrease"><span>Decrease</span></button><button class="qty-change increase"><span>Increase</span></button>'
		);
	if (!$(this).val() || $(this).val() === "") {
		$(this).val("0");
	}
	$(this).on("change", function () {
		checkTicketTotals();
	});
});
function checkTicketTotals() {
	let ticketTotal = 0;
	$(".ginput_quantity").each(function () {
		let tempQty = +$(this).val();
		ticketTotal = ticketTotal + tempQty;
	});

	// console.log("Tix total: " + ticketTotal);

	if (ticketTotal >= 5) {
		$("body").addClass("ticket-limit-reached");
	} else {
		$("body").removeClass("ticket-limit-reached");
	}
	if (ticketTotal > 1) {
		$("#field_7_23").addClass("has-guests");
	} else {
		$("#field_7_23").removeClass("has-guests");
	}
}
$(".qty-change").each(function () {
	$(this).on("click", function (e) {
		e.preventDefault();
		let currVal = $(this).parent().find(".ginput_quantity").val();
		currVal = parseInt(currVal);
		if ($(this).hasClass("decrease")) {
			currVal = currVal - 1;
		} else {
			currVal = currVal + 1;
		}
		if (currVal < 0) {
			currVal = 0;
		}

		$(this).parent().find(".ginput_quantity").val(currVal).change();
	});
});

var currLang = "en";
if (
	$("body").hasClass("lang-fr_FR") ||
	$("body").hasClass("translatepress-fr_FR")
) {
	currLang = "fr";
} else if (
	$("body").hasClass("lang-de_DE") ||
	$("body").hasClass("translatepress-de_DE")
) {
	currLang = "de";
}

$(".ginput_product_price").each(function () {
	var thisHTML = $(this).html();
	thisHTML = thisHTML.replace(",00 €", "€");
	if (thisHTML === "0€") {
		if (currLang === "fr") {
			thisHTML = "Gratuit";
		} else if (currLang === "de") {
			thisHTML = "Kostenlos";
		} else {
			thisHTML = "Free";
		}
	}
	$(this).html(thisHTML);
});

// Preventative Image saving
$("img").on("dragstart", function (event) {
	event.preventDefault();
});
$("body").on("contextmenu", "img", function (e) {
	return false;
});
$(".form-fill").on("click", function (e) {
	e.preventDefault();
	$("#input_4_3").val("2|50").change();
	$("#input_4_4").val("0|0").change();
	$("#input_4_6").val("Demo Book Name");
	$("#input_4_7").val("dan@chips.nyc");
});

export { app };
