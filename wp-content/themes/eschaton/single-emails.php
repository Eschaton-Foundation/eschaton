<?php

require('./wp-blog-header.php');

$emailTextPart = '';

ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title></title>
	<style type="text/css">
		/*<![CDATA[*/
		#outlook a {
			padding: 0;
		}

		body {
			width: 100% !important;
			-webkit-text-size-adjust: 100%;
			-ms-text-size-adjust: 100%;
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
			font-family: "Adobe Garamond Pro", Constantia, "Times New Roman", Times, serif;
			font-size: 22px;
			line-height: 1.1818;
			background-color: #fff;
		}

		body,
		td,
		td a,
		input,
		textarea,
		select {
			font-family: "Adobe Garamond Pro", Constantia, "Times New Roman", Times, serif;
			font-size: 18px;
			line-height: 1.25;
		}

		body.plus,
		td.plus, 
		td.plus a {
			font-size: 22px;
			line-height: 1.1818;
		}

		a {
			color: inherit;
			text-decoration-thickness: 1px;
			text-underline-offset: 0.15em;
		}

		.logo-wrap {
			max-width: 650px;
			margin: 50px auto 80px;
		}

		.content-row {
			margin-bottom: 30px;
		}

		.img-caption {
			font-size: 15px;
			margin-top: 10px;

		}


		.ExternalClass {
			width: 100%;
		}

		.ExternalClass,
		.ExternalClass p,
		.ExternalClass span,
		.ExternalClass font,
		.ExternalClass td,
		.ExternalClass div {
			line-height: 100%;
		}

		#backgroundTable {
			margin: 0 0 150px;
			padding: 0;
			width: 100% !important;
			background-color: white;
			color: black;
		}

		.booking-summary-wrap {
			background: rgba(153, 153, 140, 0.1);
			border-radius: 4px;
			padding: 1em;
		}

		.blok-img img {
			width: 100%;
			height: auto;
		}

		.table-w-icon img {
			display: block;
			max-width: 70%;
			margin: auto;
			height: auto;
		}

		h2 {
			font-size: 14px;
			margin-top: 4em;
			letter-spacing: .05em;
			line-height: 25px;
			text-transform: uppercase;
			text-align: center;
			font-weight: normal;
		}

		ul,
		ol {
			padding-left: 1.5em;
		}

		ul,
		ol,
		li {
			margin-bottom: .375em;
		}

		.col-single img {
			display: block;
			width: 100%;
			height: auto;

		}

		/*]]>*/
	</style>

</head>

<body>
	<div style="display:none;font-size:0;line-height:0;max-height:0;mso-hide:all">
		<?php if (get_field("email_preview_text")) {
			echo get_field("email_preview_text");
		} else {
			// echo get_the_title();
		} ?>
	</div>
	<!-- Wrapper/Container Table: Use a wrapper table to control the width and the background color consistently of your email. Use this approach instead of setting attributes on the body tag. -->
	<table cellpadding="0" cellspacing="0" border="0" id="backgroundTable">
		<tr>
			<td class="backgroundTD">

				<table class="logo-wrap" width="100%" align="center" class="content-row">
					<tr>
						<td align="left"><img src="https://eschaton-foundation.com/wp-content/themes/eschaton/assets/eschaton-logo@2x.png" width="382" height="25" border="0" alt="Eschaton—Anselm Kiefer Foundation"></td>
					</tr>
				</table>

				<?php
				$contents = get_field("email_contents");
				if (!empty($contents)) {
					foreach ($contents as $cSingle) {
						// print_r($cSingle);
						$cType = $cSingle['acf_fc_layout'];
						if ($cType === 'text') {
							$emailTextPart .= strip_tags($cSingle['text']) . '

					';
				?>

							<table cellpadding=" 0" cellspacing="0" border="0" align="center" class="content-row">
								<tr>
									<td width="650" valign="top" <?php
																	if (isset($cSingle['headline']) && $cSingle['headline'] === 'plus') {
																		echo 'class="plus"';
																	}
																	?>>
										<?php echo $cSingle['text']; ?>
									</td>
								</tr>
							</table>
						<?php } else if ($cType === 'table') {
							$emailTextPart .= strip_tags($cSingle['text']) . '

					';
						?>

							<table cellpadding="0" cellspacing="0" border="0" align="center" class="content-row table-w-icon">
								<?php $tRows = $cSingle['rows'];
								if (!empty($tRows)) {
									foreach ($tRows as $tRow) { ?>
										<tr>
											<td valign="top" width="50">
												&nbsp;<br>
												<?php echo wp_get_attachment_image($tRow['icon'], 'full'); ?>
											</td>
											<td width="20">&nbsp;</td>
											<td width="600" valign="top">
												<?php echo $tRow['text']; ?>
											</td>
										</tr>
								<?php }
								} ?>
							</table>
						<?php } else if ($cType === 'image') { ?>
							<table cellpadding="0" cellspacing="0" border="0" align="center" class="content-row">
								<tr>
									<td width="650" class="blok-img" valign="top"><?php
																					echo wp_get_attachment_image($cSingle['image'], 'large');
																					// $imgCaption = wp_get_attachment_caption($cSingle['image']);
																					$imgCaption = null;
																					if (isset($cSingle['caption'])) {
																						$imgCaption = $cSingle['caption'];
																					}
																					if ($imgCaption && !empty($imgCaption)) {
																						echo '<div class="img-caption caption">' . $imgCaption . '</div>';
																					}
																					?></td>
								</tr>
							</table>

						<?php } else if ($cType === 'two-column') {
						?>
							<table cellpadding="0" cellspacing="0" border="0" align="center" class="content-row">
								<?php if (isset($cSingle['headline']) && $cSingle['headline'] && $cSingle['headline'] !== '') {
									echo '<tr><td colspan="3"><h2>' . $cSingle['headline'] . '</h2></td></tr>';
								} ?>
								<tr>
									<td width="310" class="col-single col-1" valign="top">
										<?php echo $cSingle['column_1']; ?>
									</td>
									<td width="30">&nbsp;</td>
									<td width="310" class="col-single col-2" valign="top">
										<?php echo $cSingle['column_2']; ?>
									</td>
								</tr>
							</table>
				<?php
						}
					}
				}

				?>

			</td>
		</tr>
	</table>

</body>

</html>
<?php
$emailHTML = ob_get_contents();
ob_end_clean();

// $emailHTML = str_replace('juddfoundation.test', 'juddfoundation.com', $emailHTML);

echo $emailHTML;
?>
<style>
	.email-admin-drawer {
		position: fixed;
		top: 0;
		right: 0;
		width: 350px;
		height: 100%;
		background: white;
		z-index: 10;
		transition-duration: 0.15s;
		transform: translateX(0);
	}

	.closed {
		transform: translateX(100%);
	}

	.email-admin-drawer-contents {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		padding: 1.5rem;
		overflow: auto;
	}

	.email-admin-drawer div {
		font-family: sans-serif;
		font-size: 16px;
	}

	.email-admin-drawer textarea {
		width: 100%;
		height: 150px;
		border: solid 1px #cacaca;
		padding: 5px;
	}

	.email-admin-drawer button {
		background: none;
		border: solid 1px #cacaca;
		padding: 0.5rem;
		display: inline-block;
		cursor: pointer;
	}

	.email-admin-drawer button:HOVER {
		border-color: #999;
	}

	.copy-button {
		width: 100%;
		margin: 0.25rem 0 4rem;
	}

	.email-admin-drawer button.close-drawer {
		position: absolute;
		right: 100%;
		top: 0;
		width: 40px;
		height: 40px;
		border: solid 1px #cacaca;
		background: white;
		cursor: pointer;
		border-right: none;
		border-top: none;
	}

	.close-drawer:BEFORE {
		content: "";
		display: block;
		position: absolute;
		top: 50%;
		left: 40%;
		width: 12px;
		height: 12px;
		border-right: solid 1px #cacaca;
		border-bottom: solid 1px #cacaca;
		transform: translate(-50%, -50%) rotate(-45deg);
	}

	.closed .close-drawer:BEFORE {
		left: 60%;
		transform: translate(-50%, -50%) rotate(135deg);
	}

	.email-admin-drawer ul {
		font-size: 14px;
		margin-left: 0;
		padding-left: 0;
		list-style: none;
	}

	.email-admin-drawer ul li {
		margin-bottom: 0.5rem;
	}
</style>
<div class="email-admin-drawer">
	<button class="close-drawer" onclick="toggledrawer()"><span></span></button>
	<div class="email-admin-drawer-contents">
		<p>Email admin panel</p>
		<textarea id="emailcontents" class="email-contents"><?php echo $emailHTML; ?></textarea>
		<button class="copy-button" onclick="copyEmail()">Copy email HTML to clipboard</button>
		<p>NOTE: Email contents must be copied here and pasted into the "<a href="/wp-admin/admin.php?page=acf-options-emails-formatted">Emails Formatted</a>" section.</p>
	</div>
</div>
<script src="//code.jquery.com/jquery-3.2.1.min.js"></script>
<script defer src="/wp-content/themes/eschaton/js/vendor/emailinline.js"></script>
<script>
	function copyEmail() {
		var copyText = document.getElementById("emailcontents");
		copyText.select();
		copyText.setSelectionRange(0, 99999); /* For mobile devices */
		document.execCommand("copy");
		document.querySelector('.copy-button').innerHTML = 'Copied!';
	}

	function toggledrawer() {
		document.querySelector('.email-admin-drawer').classList.toggle("closed");
	}
</script>