<?php

$footerwidgets = is_active_sidebar('footer-widget-area-1') + is_active_sidebar('footer-widget-area-2') + is_active_sidebar('footer-widget-area-3') + is_active_sidebar('footer-widget-area-4');
// default
$footergrid = "one_fourth";
// if only one
if ($footerwidgets == "1") {
$footergrid = "span_12";
// if two, split in half
} elseif ($footerwidgets == "2") {
$footergrid = "span_6";
// if three, divide in thirds
} elseif ($footerwidgets == "3") {
$footergrid = "span_4";
// if four, split in fourths
} elseif ($footerwidgets == "4") {
$footergrid = "span_3";
}

?>

<?php if ($footerwidgets) : ?>

<?php if (is_active_sidebar('footer-widget-area-1')) : ?>
<div class="<?php echo $footergrid;?>">
	<?php dynamic_sidebar('footer-widget-area-1'); ?>
</div>
<?php endif;?>

<?php if (is_active_sidebar('footer-widget-area-2')) : $last = ($footerwidgets == '2' ? ' last' : false);?>
<div class="<?php echo $footergrid.$last;?>">
	  <?php dynamic_sidebar('footer-widget-area-2'); ?>
</div>
<?php endif;?>

<?php if (is_active_sidebar('footer-widget-area-3')) : $last = ($footerwidgets == '3' ? ' last' : false);?>
<div class="<?php echo $footergrid.$last;?>">
	  <?php dynamic_sidebar('footer-widget-area-3'); ?>
</div>
<?php endif;?>

<?php if (is_active_sidebar('footer-widget-area-4')) : $last = ($footerwidgets == '4' ? ' last' : false);?>
<div class="<?php echo $footergrid.$last;?>">
		  <?php dynamic_sidebar('footer-widget-area-4'); ?>
</div>
<?php endif;?>
<div class="clear"></div>

<?php endif;?>
