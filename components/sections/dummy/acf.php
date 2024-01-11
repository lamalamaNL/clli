<?php

$title = 'Dummy';
$name = 'dummy';
$key = 'RuumSp3dvpXW';

$groupFields = [
    [
        'key' => $key . '_01',
        'label' => 'Text',
        'name' => 'text',
        'type' => 'group',
        'sub_fields' => llGetFields('part', 'wysiwyg', $key . '_01')
    ],
    [
        'key' => $key . '_02',
        'label' => 'Image',
        'name' => 'image',
        'type' => 'group',
        'sub_fields' => llGetFields('part', 'image', $key . '_02')
    ],
    [
        'key' => $key . '_03',
        'label' => 'Video',
        'name' => 'video',
        'type' => 'group',
        'sub_fields' => llGetFields('part', 'video_hls', $key . '_03')
    ]
];
