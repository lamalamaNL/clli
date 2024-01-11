<?php
if (!isset($section)) {
    $section = false;
} elseif (!isset($key)) {
    $key = false;
}

$wysiwyg = llField('text', $key, $section)['wysiwyg'];
$image = llField('image', $key, $section)['image'];
$video = llField('video', $key, $section);

if ($wysiwyg || $image || $video) : ?>
<?php llSectionHeader('dummy' , false, false, false, 'dummy'); ?>
    <?php llPart('wysiwyg', [
        'wysiwyg' => $wysiwyg
    ]); ?>
    <?php llPart('image', [
        'image' => $image,
        'isolated' => llField('image', $key, $section)['isolated'] ?? true
    ]); ?>
    <?php llPart('video_hls', [
        'video' => $video,
    ]); ?>
<?php llSectionFooter(); ?>
<?php endif; ?>
