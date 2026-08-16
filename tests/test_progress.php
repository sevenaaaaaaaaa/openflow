<?php
/**
 * 测试: ProgressSystem
 */
require_once __DIR__ . '/../lib/ProgressSystem.php';

test('progress_file returns valid path', function() {
    $path = progress_file();
    assert_true(strpos($path, 'progress.json') !== false, 'Path should contain progress.json');
    assert_true(strpos($path, DATA_DIR) !== false, 'Path should be under DATA_DIR');
});

test('progress_all returns array', function() {
    $result = progress_all();
    assert_true(is_array($result), 'Should return array');
});

test('progress_set and progress_get round-trip', function() {
    $memberId = 'test_member_' . md5(uniqid());
    $courseId = 'test_course_' . md5(uniqid());
    $lessonId = 'test_lesson_' . md5(uniqid());
    
    progress_set($memberId, $courseId, $lessonId, [
        'done' => false,
        'position' => 120,
        'duration' => 600,
    ]);
    
    $result = progress_get($memberId, $courseId);
    assert_true(isset($result[$lessonId]), 'Should have lesson entry');
    assert_eq(120, $result[$lessonId]['position'], 'Position should match');
    assert_false($result[$lessonId]['done'], 'Should not be done');
    
    // Clean up
    $all = progress_all();
    unset($all[$memberId]);
    progress_save($all);
});

test('progress_done marks lesson as complete', function() {
    $memberId = 'test_member_' . md5(uniqid());
    $courseId = 'test_course_' . md5(uniqid());
    $lessonId = 'test_lesson_' . md5(uniqid());
    
    progress_done($memberId, $courseId, $lessonId);
    $result = progress_get($memberId, $courseId);
    assert_true($result[$lessonId]['done'] === true, 'Lesson should be marked done');
    
    // Clean up
    $all = progress_all();
    unset($all[$memberId]);
    progress_save($all);
});

test('progress_summary calculates correctly', function() {
    $course = [
        'chapters' => [
            [
                'id' => 'ch1',
                'title' => 'Chapter 1',
                'lessons' => [
                    ['id' => 'l1', 'title' => 'Lesson 1'],
                    ['id' => 'l2', 'title' => 'Lesson 2'],
                ],
            ],
            [
                'id' => 'ch2',
                'title' => 'Chapter 2',
                'lessons' => [
                    ['id' => 'l3', 'title' => 'Lesson 3'],
                ],
            ],
        ],
    ];
    
    $memberId = 'test_summary_' . md5(uniqid());
    $courseId = 'test_course_' . md5(uniqid());
    
    progress_done($memberId, $courseId, 'l1');
    
    $summary = progress_summary($memberId, $courseId, $course);
    assert_eq(3, $summary['total'], 'Total should be 3');
    assert_eq(1, $summary['done'], 'Done should be 1');
    assert_true(abs(33 - $summary['percent']) < 1, 'Percent should be ~33');
    
    // Clean up
    $all = progress_all();
    unset($all[$memberId]);
    progress_save($all);
});

test('progress_resume finds unfinished lesson', function() {
    $course = [
        'chapters' => [
            [
                'id' => 'ch1',
                'title' => 'Chapter 1',
                'lessons' => [
                    ['id' => 'l1', 'title' => 'Lesson 1'],
                    ['id' => 'l2', 'title' => 'Lesson 2'],
                ],
            ],
        ],
    ];
    
    $memberId = 'test_resume_' . md5(uniqid());
    $courseId = 'test_course_' . md5(uniqid());
    
    progress_done($memberId, $courseId, 'l1');
    progress_set($memberId, $courseId, 'l2', ['position' => 60, 'done' => false]);
    
    $resume = progress_resume($memberId, $courseId, $course);
    assert_true($resume !== null, 'Should find resume point');
    assert_eq('l2', $resume['lesson_id'], 'Should resume at l2');
    assert_eq(60, $resume['position'], 'Position should be 60');
    
    // Clean up
    $all = progress_all();
    unset($all[$memberId]);
    progress_save($all);
});
