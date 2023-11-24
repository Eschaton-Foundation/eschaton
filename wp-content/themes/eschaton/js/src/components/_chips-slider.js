export default class {
	constructor(options) {
		this.defaults = {};
		this.options = {};
		this.totalSlides = 1;
		this.currentSlide = 1;
		this.domEl = null;
		this.slideTimer = 4000;
		Object.assign(this.options, this.defaults, options);

		this.namespace = "CHIPSSLIDER";
		this.init();
	}

	init() {
		this.bindEvents(true);

		if (this.options && this.options.targetEl) {
			this.domEl = $(this.options.targetEl);
			this.totalSlides = this.domEl.find(".slide").length;
		}

		this.slideshowTimer = setTimeout(() => {
			this.cycleImg();
		}, this.slideTimer);
	}

	cycleImg() {
		this.currentSlide = this.currentSlide + 1;
		if (this.currentSlide > this.totalSlides) {
			this.currentSlide = 1;
		}
		$(".slideshow .active").removeClass("active");
		$(".slideshow .slide")
			.eq(this.currentSlide - 1)
			.addClass("active");

		this.slideshowTimer = setTimeout(() => {
			this.cycleImg();
		}, this.slideTimer);
	}

	bindEvents(bindDirection) {
		this.sliderObj = [];

		if (bindDirection) {
		}
	}

	destroy() {
		this.bindEvents(false);
		clearTimeout(this.slideshowTimer);
	}
}
