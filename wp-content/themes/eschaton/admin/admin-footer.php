<script src="<?php echo get_bloginfo('template_directory') . '/js/vendor/jquery-3.6.0.min.js'; ?>"></script>
<script>

</script>
<div id="result"></div>
<script>
	var autoplayNext = false;
	$(".aj-queue").on("click", function(e) {
		e.preventDefault();

		var queueType = $(this).data("process");
		runSingle($(this).data("id"), $(this).data("process"));
	});

	function runSingle(postID, processName) {
		$(".processing").removeClass("processing");
		$("[data-id='" + postID + "']").addClass("processing");
		console.log("loading up on " + postID);
		$.ajax({
				url: "/?get=" + processName + "&fileName=" + postID,
			})
			.done(function(data) {
				// $("[data-id='" + postID + "']").remove();
				$('[data-id="' + postID + '"]').remove();

				console.log("Checking if autoplay is on");
				if (autoplayNext) {
					console.log("autoplaying in 3..2..1..");
					setTimeout(function() {
						console.log("Firing next item");
						runNext();
					}, 1000);
				}
			});
	}

	function runNext() {
		if ($(".aj-queue").length > 0) {
			$(".aj-queue").eq(0).trigger("click");
		}
	}

	function autoplayQueue() {
		autoplayNext = true;
		runNext();
	}
</script>
</body>

</html>