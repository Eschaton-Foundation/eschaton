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
// $(".menu-toggle").on("click", function () {
// 	$("body").toggleClass("menu-on");
// });

var mouseTimer = null;
$("header,.menu-primary-nav-container").hover(
	function () {
		clearTimeout(mouseTimer);
		$("body").addClass("menu-on");
	},
	function () {
		//mouseTimer = setTimeout(function () {
		$("body").removeClass("menu-on");
		//}, 100);
	}
);

$(".img-enlarge").on("click", function (e) {
	e.preventDefault();
	var targetURL = $(this).attr("href");

	$(".modal-content-scroll").html(
		'<img src="' + targetURL + '" class="modal-img" border="0" />'
	);
	$("body").addClass("modal-on");
	$(".modal").addClass("modal-interact");
	initZoom();
});
$(".modal-close").on("click", closeModal);

function closeModal() {
	$("body").removeClass("modal-on");
	zoomDOM.dispose();
}

var zoomDOM = null;
function initZoom() {
	var element = document.querySelector("#zoomModal");
	zoomDOM = panzoom(element, {
		maxZoom: 2.5,
		minZoom: 1,
		onTouch: function (e) {
			return false;
		},
	});
}
