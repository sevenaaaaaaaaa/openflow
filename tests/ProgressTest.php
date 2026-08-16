<?php
/**
 * 测试: ProgressSystem (PHPUnit)
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProgressTest extends TestCase {
    public function testProgressFileReturnsValidPath(): void {
        $path = progress_file();
        $this->assertStringContainsString('progress.json', $path);
        $this->assertStringContainsString(DATA_DIR, $path);
    }

    public function testProgressAllReturnsArray(): void {
        $result = progress_all();
        $this->assertIsArray($result);
    }

    public function testProgressSetAndGetRoundTrip(): void {
        $memberId = 'test_member_' . md5(uniqid());
        $courseId = 'test_course_' . md5(uniqid());
        $lessonId = 'test_lesson_' . md5(uniqid());
        
        progress_set($memberId, $courseId, $lessonId, [
            'done' => false,
            'position' => 120,
            'duration' => 600,
        ]);
        
        $result = progress_get($memberId, $courseId);
        $this->assertArrayHasKey($lessonId, $result);
        $this->assertSame(120, $result[$lessonId]['position']);
        $this->assertFalse($result[$lessonId]['done']);
        
        // Cleanup
        $all = progress_all();
        unset($all[$memberId]);
        progress_save($all);
    }

    public function testProgressDoneMarksLessonComplete(): void {
        $memberId = 'test_member_' . md5(uniqid());
        $courseId = 'test_course_' . md5(uniqid());
        $lessonId = 'test_lesson_' . md5(uniqid());
        
        progress_done($memberId, $courseId, $lessonId);
        $result = progress_get($memberId, $courseId);
        $this->assertTrue($result[$lessonId]['done'] === true);
        
        // Cleanup
        $all = progress_all();
        unset($all[$memberId]);
        progress_save($all);
    }

    public function testProgressSummaryCalculatesCorrectly(): void {
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
        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['done']);
        $this->assertLessThan(1, abs(33 - $summary['percent']));
        
        // Cleanup
        $all = progress_all();
        unset($all[$memberId]);
        progress_save($all);
    }

    public function testProgressResumeFindsUnfinishedLesson(): void {
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
        $this->assertNotNull($resume);
        $this->assertSame('l2', $resume['lesson_id']);
        $this->assertSame(60, $resume['position']);
        
        // Cleanup
        $all = progress_all();
        unset($all[$memberId]);
        progress_save($all);
    }
}
