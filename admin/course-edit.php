<?php
require_once __DIR__ . '/config.php';
require_login();
require_perm('courses');

$coursesFile = DATA_DIR . '/courses/index.json';
$courses = json_read($coursesFile);

$id = $_GET['id'] ?? '';
$isNew = empty($id);
$course = [
    'id' => '', 'title' => '', 'slug' => '', 'type' => '课程', 'status' => 'draft',
    'description' => '', 'cover' => '',
    'chapters' => [], // [{id, title, lessons: [{id,title,duration}]}]
    'bundle_ids' => [],
    'seo_title' => '', 'seo_desc' => '',
    'price' => 0, 'original_price' => 0, 'duration' => '', 'instructor' => '',
    'difficulty' => 'beginner', 'mode' => 'recorded', 'rating' => 0, 'students' => 0, 'tags' => [],
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
];
if (!$isNew) {
    foreach ($courses as $c) { if ($c['id'] === $id) { $course = $c; break; } }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $isAutoSave = !empty($_POST['auto_save']);
    $course['title'] = $_POST['title'] ?? '';
    $course['slug'] = $_POST['slug'] ?? '';
    $course['type'] = $_POST['type'] ?? '课程';
    $course['status'] = $_POST['status'] ?? 'draft';
    $course['description'] = $_POST['description'] ?? '';
    $course['cover'] = $_POST['cover'] ?? '';
    $course['seo_title'] = $_POST['seo_title'] ?? '';
    $course['seo_desc'] = $_POST['seo_desc'] ?? '';
    $course['price'] = (float)($_POST['price'] ?? 0);
    $course['original_price'] = (float)($_POST['original_price'] ?? 0);
    $course['duration'] = trim($_POST['duration'] ?? '');
    $course['instructor'] = trim($_POST['instructor'] ?? '');
    $course['difficulty'] = $_POST['difficulty'] ?? 'beginner';
    $course['mode'] = $_POST['mode'] ?? 'recorded';
    $course['rating'] = (float)($_POST['rating'] ?? 0);
    $course['students'] = (int)($_POST['students'] ?? 0);
    $course['tags'] = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));
    $course['category'] = trim($_POST['category'] ?? '');
    $course['updated_at'] = date('Y-m-d H:i:s');
    if (empty($course['slug'])) { $course['slug'] = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}-]/u', '-', $course['title']); }

    // Build chapters from POST
    $chapters = [];
    $chapterTitles = $_POST['chapter_title'] ?? [];

    if (isset($_POST['flat_lessons'])) {
        // Flat mode (no chapters): all lessons go into a single auto chapter
        $flatLessons = [];
        foreach (($_POST['lesson_title'] ?? []) as $i => $lt) {
            if (trim($lt)) {
                $flatLessons[] = ['id' => 'lesson_' . substr(bin2hex(random_bytes(6)), 0, 8), 'title' => $lt, 'duration' => $_POST['lesson_duration'][$i] ?? ''];
            }
        }
        if (!empty($flatLessons)) {
            $chapters[] = ['id' => 'chapter_auto', 'title' => '课程内容', 'lessons' => $flatLessons];
        }
    } else {
        // Chapter mode
        foreach ($chapterTitles as $ci => $ct) {
            $ct = trim($ct);
            if (empty($ct)) continue;
            $chapterId = ($_POST['chapter_id'][$ci] ?? '') ?: 'chapter_' . substr(bin2hex(random_bytes(6)), 0, 8);
            $lessons = [];
            $lessonTitles = $_POST['chapter_lesson_title'][$ci] ?? [];
            foreach ($lessonTitles as $li => $lt) {
                $lt = trim($lt);
                if (empty($lt)) continue;
                $lessonId = ($_POST['chapter_lesson_id'][$ci][$li] ?? '') ?: 'lesson_' . substr(bin2hex(random_bytes(6)), 0, 8);
                $materialsRaw = trim(($_POST['chapter_lesson_materials'][$ci][$li] ?? ''));
                $materials = $materialsRaw ? array_filter(array_map('trim', explode(',', $materialsRaw))) : [];
                 $lessons[] = [
                     'id' => $lessonId,
                     'title' => $lt,
                     'duration' => ($_POST['chapter_lesson_duration'][$ci][$li] ?? '') ?: '',
                     'video' => trim($_POST['chapter_lesson_video'][$ci][$li] ?? ''),
                     'description' => trim($_POST['chapter_lesson_desc'][$ci][$li] ?? ''),
                     'materials' => array_values($materials),
                     'type' => $_POST['chapter_lesson_type'][$ci][$li] ?? 'video',
                     'free' => !empty($_POST['chapter_lesson_free'][$ci][$li]),
                     'questions' => json_decode($_POST['chapter_lesson_questions'][$ci][$li] ?? '[]', true) ?: [],
                 ];
            }
            $chapters[] = ['id' => $chapterId, 'title' => $ct, 'lessons' => $lessons];
        }
    }
    $course['chapters'] = $chapters;

    // Bundle IDs
    $course['bundle_ids'] = array_filter(explode(',', $_POST['bundle_ids'] ?? ''));

    if ($isNew) {
        $course['id'] = 'course_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $course['created_at'] = date('Y-m-d H:i:s');
        $courses[] = $course;
    } else {
        foreach ($courses as &$c) { if ($c['id'] === $id) { $c = $course; break; } }
    }
    json_write($coursesFile, $courses);
    if ($isAutoSave) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'id' => $course['id']]);
        exit;
    }
    flash('success', $isNew ? '课程已创建' : '课程已保存');
    header('Location: /xmp/course-edit?id=' . urlencode($course['id']));
    exit;
}

// Detect if old flat format needs migration
$hasChapters = !empty($course['chapters']);
$flatMode = !$hasChapters && !empty($course['lessons']);

admin_header($isNew ? '创建课程' : '编辑课程');
?>
<style>
.chapter-box{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:12px;overflow:hidden}
.chapter-header{display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--surface-2);border-bottom:1px solid var(--border)}
.chapter-header input[type=text]{flex:1;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:14px;background:var(--surface)}
.chapter-body{padding:12px 14px}
.lesson-row{display:flex;gap:8px;margin-bottom:8px;align-items:center;cursor:grab}
.lesson-row:active{cursor:grabbing}
.chapter-box{cursor:grab}
.chapter-box:active{cursor:grabbing}
.chapter-box.drag-over{outline:2px dashed var(--accent)}
.lesson-row input[type=text]{padding:6px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:13px}
.lesson-row .num{font-size:12px;color:var(--text-3);width:20px;text-align:center;font-family:var(--mono)}
.lesson-detail{display:none;padding:12px;background:var(--surface-2);border-radius:8px;margin:8px 0;border:1px solid var(--border)}
.lesson-detail.show{display:block}
.lesson-detail .field{margin-bottom:8px}
.lesson-detail label{font-size:11px;color:var(--muted);margin-bottom:2px;display:block}
.lesson-detail input,.lesson-detail textarea,.lesson-detail select{width:100%;padding:6px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;background:var(--surface)}
.lesson-detail textarea{min-height:60px;resize:vertical}
.lesson-expand{cursor:pointer;color:var(--muted);font-size:11px;padding:2px 8px;border-radius:4px;border:1px solid var(--border);background:var(--surface)}
.lesson-expand:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
</style>
<div class="admin-layout">
  <?php admin_sidebar('courses'); ?>
  <div class="main">
    <div class="flex items-center gap-4 mb-4">
      <h1 style="margin-bottom:0"><?=$isNew?'创建课程':'编辑课程'?></h1>
      <a href="courses.php" class="btn btn-ghost ml-auto">← 返回</a>
    </div>

    <form method="post" id="courseForm">
      <?= csrf_field() ?>
      <!-- Basic Info -->
      <div class="card">
        <h2>基本信息</h2>
        <div class="field-row">
          <div class="field"><label>课程名称</label><input type="text" name="title" value="<?=htmlspecialchars($course['title'])?>" required></div>
          <div class="field"><label>Slug</label><input type="text" name="slug" value="<?=htmlspecialchars($course['slug'])?>" placeholder="自动生成"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>类型</label><select name="type"><option value="课程" <?=$course['type']==='课程'?'selected':''?>>单课</option><option value="专栏" <?=$course['type']==='专栏'?'selected':''?>>专栏</option><option value="认证课" <?=$course['type']==='认证课'?'selected':''?>>认证课</option></select></div>
          <div class="field"><label>状态</label><select name="status"><option value="draft" <?=$course['status']==='draft'?'selected':''?>>草稿</option><option value="published" <?=$course['status']==='published'?'selected':''?>>已发布</option></select></div>
        </div>
        <div class="field"><label>描述</label><textarea name="description" rows="3"><?=htmlspecialchars($course['description'])?></textarea></div>
        <div class="field-row">
          <div class="field"><label>价格 <span class="hint">· 元</span></label><input type="number" name="price" value="<?=htmlspecialchars($course['price'] ?? 0)?>" min="0" step="0.01"></div>
          <div class="field"><label>原价 <span class="hint">· 划线价</span></label><input type="number" name="original_price" value="<?=htmlspecialchars($course['original_price'] ?? 0)?>" min="0" step="0.01"></div>
          <div class="field"><label>总时长</label><input type="text" name="duration" value="<?=htmlspecialchars($course['duration'] ?? '')?>" placeholder="如 5 小时 20 分"></div>
        </div>
        <div class="field-row">
          <div class="field"><label>讲师</label><input type="text" name="instructor" value="<?=htmlspecialchars($course['instructor'] ?? '')?>" placeholder="讲师姓名"></div>
          <div class="field"><label>难度</label><select name="difficulty"><option value="beginner" <?=($course['difficulty']??'')==='beginner'?'selected':''?>>入门</option><option value="intermediate" <?=($course['difficulty']??'')==='intermediate'?'selected':''?>>进阶</option><option value="advanced" <?=($course['difficulty']??'')==='advanced'?'selected':''?>>高级</option></select></div>
          <div class="field"><label>学习模式</label><select name="mode"><option value="recorded" <?=($course['mode']??'')==='recorded'?'selected':''?>>录播</option><option value="live" <?=($course['mode']??'')==='live'?'selected':''?>>直播</option><option value="offline" <?=($course['mode']??'')==='offline'?'selected':''?>>线下</option><option value="hybrid" <?=($course['mode']??'')==='hybrid'?'selected':''?>>混合</option></select></div>
        </div>
        <div class="field-row">
          <div class="field"><label>评分 <span class="hint">· 0-5</span></label><input type="number" name="rating" value="<?=htmlspecialchars($course['rating'] ?? 0)?>" min="0" max="5" step="0.1"></div>
          <div class="field"><label>学习人数</label><input type="number" name="students" value="<?=htmlspecialchars($course['students'] ?? 0)?>" min="0"></div>
          <div class="field"><label>分类</label><select name="category"><option value="">未分类</option><?php foreach (get_categories('course') as $c): ?><option value="<?=htmlspecialchars($c['key'])?>" <?=($course['category'] ?? '')===$c['key']?'selected':''?>><?=htmlspecialchars($c['name'])?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field"><label>标签 <span class="hint">· 逗号分隔</span></label><input type="text" name="tags" value="<?=htmlspecialchars(implode(',', $course['tags'] ?? []))?>" placeholder="领导力, 管理"></div>
        <div class="field"><label>封面图</label><input type="text" name="cover" value="<?=htmlspecialchars($course['cover'])?>" placeholder="uploads/..."></div>
      </div>

      <!-- Chapter / Lesson Structure -->
      <div class="card">
        <div class="flex items-center gap-4 mb-4">
          <h2 style="margin-bottom:0">课程结构</h2>
          <span class="text-sm text-muted">课程 → 章节 → 课时 三级 · 按住空白处可拖拽排序</span>
          <div style="margin-left:auto;display:flex;gap:8px">
            <button type="button" class="btn btn-ghost btn-sm" onclick="addChapter()">+ 添加章节</button>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addFlatLesson()">+ 直接加课时</button>
          </div>
        </div>

        <!-- Chapters container -->
        <div id="chaptersContainer">
          <?php if ($flatMode):
            // Render flat lessons as if they're in a single chapter
            $flatLessons = $course['lessons'];
          ?>
          <div class="chapter-box" data-index="0">
            <div class="chapter-header">
              <span style="font-weight:600;font-size:14px">📖 课程内容</span>
              <input type="hidden" name="chapter_id[0]" value="chapter_auto">
              <input type="hidden" name="chapter_title[0]" value="课程内容">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeChapter(this)" style="margin-left:auto">删除章节</button>
            </div>
            <div class="chapter-body" data-chapter="0">
              <?php foreach ($flatLessons as $li => $l): ?>
              <div class="lesson-row" data-lesson-id="<?=htmlspecialchars($l['id'] ?? '')?>">
                <span class="num"><?=$li+1?></span>
                <input type="text" name="chapter_lesson_title[0][]" value="<?=htmlspecialchars($l['title'])?>" placeholder="课时名称" style="flex:1">
                <input type="text" name="chapter_lesson_duration[0][]" value="<?=htmlspecialchars($l['duration'])?>" placeholder="时长" style="width:80px">
                <button type="button" class="lesson-expand" onclick="toggleLessonDetail(this)" title="展开详细设置">⚙</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.lesson-row').remove()">✕</button>
                <input type="hidden" name="chapter_lesson_id[0][]" value="<?=htmlspecialchars($l['id'] ?? 'lesson_' . substr(bin2hex(random_bytes(6)), 0, 8))?>">
                <div class="lesson-detail">
                  <div class="field"><label>视频地址 <span class="hint">· URL 或 embed 代码</span></label><input type="text" name="chapter_lesson_video[0][]" value="<?=htmlspecialchars($l['video'] ?? '')?>" placeholder="https://..."></div>
                  <div class="field"><label>课时简介</label><textarea name="chapter_lesson_desc[0][]" rows="2" placeholder="本节内容概要..."><?=htmlspecialchars($l['description'] ?? '')?></textarea></div>
                  <div class="field"><label>配套资料 <span class="hint">· URL，多个逗号分隔</span></label><input type="text" name="chapter_lesson_materials[0][]" value="<?=htmlspecialchars(implode(',', $l['materials'] ?? []))?>" placeholder="https://...pdf,https://...ppt"></div>
                  <div class="field-row">
                    <div class="field"><label>课时类型</label><select name="chapter_lesson_type[0][]" onchange="toggleQuizQ(this)"><option value="video" <?=($l['type'] ?? 'video')==='video'?'selected':''?>>视频</option><option value="text" <?=($l['type'] ?? '')==='text'?'selected':''?>>图文</option><option value="quiz" <?=($l['type'] ?? '')==='quiz'?'selected':''?>>测验</option><option value="download" <?=($l['type'] ?? '')==='download'?'selected':''?>>资料下载</option></select></div>
                    <div class="field"><label>试看 <span class="hint">· 免费可看</span></label><select name="chapter_lesson_free[0][]"><option value="0" <?=empty($l['free'])?'selected':''?>>否</option><option value="1" <?=!empty($l['free'])?'selected':''?>>是</option></select></div>
                  </div>
                  <div class="field quiz-q-field" style="<?=($l['type'] ?? '')==='quiz'?'':'display:none'?>">
                    <label>测验题目 <span class="hint">· JSON 格式，示例：</span><button type="button" class="btn btn-ghost btn-sm" style="padding:0 8px;font-size:10px" onclick="fillQuizExample(this)">填入示例</button></label>
                    <textarea name="chapter_lesson_questions[0][]" rows="3" placeholder='[{"type":"single","q":"什么是增长系统？","options":["A.内容","B.流程","C.两者"],"answer":"C"}]'><?=htmlspecialchars(json_encode($l['questions'] ?? [], JSON_UNESCAPED_UNICODE))?></textarea>
                    <div class="hint" style="font-size:11px">type: single单选/multi多选/judge判断 · answer: 单选填选项字母(A)，多选填"A,C"，判断填"对/错" · 通过线 80%</div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="padding:8px 14px;border-top:1px solid var(--border)"><button type="button" class="btn btn-ghost btn-sm" onclick="addLessonToChapter(0)">+ 添加课时</button></div>
          </div>
          <?php elseif ($hasChapters):
            foreach ($course['chapters'] as $ci => $ch):
          ?>
          <div class="chapter-box" data-index="<?=$ci?>">
            <div class="chapter-header">
              <span style="font-weight:600;font-size:14px">📖 章节</span>
              <input type="text" name="chapter_title[<?=$ci?>]" value="<?=htmlspecialchars($ch['title'])?>" placeholder="章节名称">
              <input type="hidden" name="chapter_id[<?=$ci?>]" value="<?=htmlspecialchars($ch['id'])?>">
              <button type="button" class="btn btn-danger btn-sm" onclick="removeChapter(this)" style="margin-left:auto">删除</button>
            </div>
            <div class="chapter-body" data-chapter="<?=$ci?>">
              <?php foreach (($ch['lessons'] ?? []) as $li => $l): ?>
              <div class="lesson-row" data-lesson-id="<?=htmlspecialchars($l['id'] ?? '')?>">
                <span class="num"><?=$li+1?></span>
                <input type="text" name="chapter_lesson_title[<?=$ci?>][]" value="<?=htmlspecialchars($l['title'])?>" placeholder="课时名称" style="flex:1">
                <input type="text" name="chapter_lesson_duration[<?=$ci?>][]" value="<?=htmlspecialchars($l['duration'])?>" placeholder="时长" style="width:80px">
                <button type="button" class="lesson-expand" onclick="toggleLessonDetail(this)" title="展开详细设置">⚙</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.lesson-row').remove()">✕</button>
                <input type="hidden" name="chapter_lesson_id[<?=$ci?>][]" value="<?=htmlspecialchars($l['id'] ?? 'lesson_' . substr(bin2hex(random_bytes(6)), 0, 8))?>">
                <div class="lesson-detail">
                  <div class="field"><label>视频地址 <span class="hint">· URL 或 embed 代码</span></label><input type="text" name="chapter_lesson_video[<?=$ci?>][]" value="<?=htmlspecialchars($l['video'] ?? '')?>" placeholder="https://..."></div>
                  <div class="field"><label>课时简介</label><textarea name="chapter_lesson_desc[<?=$ci?>][]" rows="2" placeholder="本节内容概要..."><?=htmlspecialchars($l['description'] ?? '')?></textarea></div>
                  <div class="field"><label>配套资料 <span class="hint">· URL，多个逗号分隔</span></label><input type="text" name="chapter_lesson_materials[<?=$ci?>][]" value="<?=htmlspecialchars(implode(',', $l['materials'] ?? []))?>" placeholder="https://...pdf,https://...ppt"></div>
                  <div class="field-row">
                    <div class="field"><label>课时类型</label><select name="chapter_lesson_type[<?=$ci?>][]" onchange="toggleQuizQ(this)"><option value="video" <?=($l['type'] ?? 'video')==='video'?'selected':''?>>视频</option><option value="text" <?=($l['type'] ?? '')==='text'?'selected':''?>>图文</option><option value="quiz" <?=($l['type'] ?? '')==='quiz'?'selected':''?>>测验</option><option value="download" <?=($l['type'] ?? '')==='download'?'selected':''?>>资料下载</option></select></div>
                    <div class="field"><label>试看 <span class="hint">· 免费可看</span></label><select name="chapter_lesson_free[<?=$ci?>][]"><option value="0" <?=empty($l['free'])?'selected':''?>>否</option><option value="1" <?=!empty($l['free'])?'selected':''?>>是</option></select></div>
                  </div>
                  <div class="field quiz-q-field" style="<?=($l['type'] ?? '')==='quiz'?'':'display:none'?>">
                    <label>测验题目 <span class="hint">· JSON 格式，示例：</span><button type="button" class="btn btn-ghost btn-sm" style="padding:0 8px;font-size:10px" onclick="fillQuizExample(this)">填入示例</button></label>
                    <textarea name="chapter_lesson_questions[<?=$ci?>][]" rows="3" placeholder='[{"type":"single","q":"什么是增长系统？","options":["A.内容","B.流程","C.两者"],"answer":"C"}]'><?=htmlspecialchars(json_encode($l['questions'] ?? [], JSON_UNESCAPED_UNICODE))?></textarea>
                    <div class="hint" style="font-size:11px">type: single单选/multi多选/judge判断 · answer: 单选填选项字母(A)，多选填"A,C"，判断填"对/错" · 通过线 80%</div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <div style="padding:8px 14px;border-top:1px solid var(--border)"><button type="button" class="btn btn-ghost btn-sm" onclick="addLessonToChapter(<?=$ci?>)">+ 添加课时</button></div>
          </div>
          <?php endforeach; endif; ?>
        </div>

        <!-- Fallback: flat lessons input (hidden, for the server-side check) -->
        <div id="flatLessonsInput" style="display:none"></div>

        <?php if (empty($course['chapters']) && !$flatMode): ?>
        <div class="empty" style="padding:24px">
          <p style="color:var(--text-3);margin-bottom:12px">还未添加任何内容</p>
          <button type="button" class="btn btn-primary btn-sm" onclick="addChapter()">添加第一个章节</button>
          <span style="margin:0 8px;color:var(--text-3)">或</span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addFlatLesson()">直接添加课时</button>
        </div>
        <?php endif; ?>
      </div>

      <!-- Bundle -->
      <?php if ($course['type'] === '专栏'): ?>
      <div class="card">
        <h2>专栏打包</h2>
        <p class="text-sm text-muted mb-4">选择要打包在一起的子课程</p>
        <div class="field"><label>子课程 ID（逗号分隔）</label><input type="text" name="bundle_ids" value="<?=htmlspecialchars(implode(',', $course['bundle_ids'] ?? []))?>" id="bundleInput" placeholder="course_xxx, course_yyy"></div>
        <div class="flex gap-2" style="flex-wrap:wrap">
          <?php foreach ($courses as $c): if ($c['id'] === $course['id']) continue; ?>
          <span class="tag-item" style="cursor:pointer" onclick="addBundle('<?=htmlspecialchars($c['id'])?>')"><?=htmlspecialchars($c['title'])?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- SEO -->
      <div class="card"><h2>SEO</h2>
        <div class="field"><label>SEO 标题</label><input type="text" name="seo_title" value="<?=htmlspecialchars($course['seo_title'])?>"></div>
        <div class="field"><label>SEO 描述</label><textarea name="seo_desc" rows="2"><?=htmlspecialchars($course['seo_desc'])?></textarea></div>
      </div>

      <button type="submit" class="btn btn-primary">保存课程</button>
    </form>
  </div>
</div>

<script>
var chapterIndex = <?=$hasChapters ? count($course['chapters']) : ($flatMode ? 1 : 0)?>;

function addChapter() {
  var idx = chapterIndex++;
  var div = document.createElement('div');
  div.className = 'chapter-box';
  div.dataset.index = idx;
  div.innerHTML =
    '<div class="chapter-header">' +
      '<span style="font-weight:600;font-size:14px">📖 章节</span>' +
      '<input type="text" name="chapter_title[' + idx + ']" placeholder="章节名称" style="flex:1">' +
      '<input type="hidden" name="chapter_id[' + idx + ']" value="chapter_' + Date.now() + '">' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="removeChapter(this)" style="margin-left:auto">删除</button>' +
    '</div>' +
    '<div class="chapter-body" data-chapter="' + idx + '">' +
    '</div>' +
    '<div style="padding:8px 14px;border-top:1px solid var(--border)">' +
      '<button type="button" class="btn btn-ghost btn-sm" onclick="addLessonToChapter(' + idx + ')">+ 添加课时</button>' +
    '</div>';
  document.getElementById('chaptersContainer').appendChild(div);
  // Remove empty state if present
  var empty = document.querySelector('#chaptersContainer .empty');
  if (empty) empty.style.display = 'none';
}

function removeChapter(btn) {
  if (document.querySelectorAll('.chapter-box').length <= 1 && !confirm('删除后章节将不留任何内容，确认?')) return;
  btn.closest('.chapter-box').remove();
}

function addLessonToChapter(chapterIdx) {
  var body = document.querySelector('.chapter-body[data-chapter="' + chapterIdx + '"]');
  if (!body) return;
  var count = body.querySelectorAll('.lesson-row').length + 1;
  var lessonId = 'lesson_' + Date.now() + '_' + Math.random().toString(36).substr(2,6);
  var row = document.createElement('div');
  row.className = 'lesson-row';
  row.dataset.lessonId = lessonId;
  row.innerHTML =
    '<span class="num">' + count + '</span>' +
    '<input type="text" name="chapter_lesson_title[' + chapterIdx + '][]" placeholder="课时名称" style="flex:1">' +
    '<input type="text" name="chapter_lesson_duration[' + chapterIdx + '][]" placeholder="时长" style="width:80px">' +
    '<button type="button" class="lesson-expand" onclick="toggleLessonDetail(this)" title="展开详细设置">⚙</button>' +
    '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.lesson-row\').remove()">✕</button>' +
    '<input type="hidden" name="chapter_lesson_id[' + chapterIdx + '][]" value="' + lessonId + '">' +
    '<div class="lesson-detail">' +
      '<div class="field"><label>视频地址 <span class="hint">· URL 或 embed 代码</span></label><input type="text" name="chapter_lesson_video[' + chapterIdx + '][]" placeholder="https://..."></div>' +
      '<div class="field"><label>课时简介</label><textarea name="chapter_lesson_desc[' + chapterIdx + '][]" rows="2" placeholder="本节内容概要..."></textarea></div>' +
      '<div class="field"><label>配套资料 <span class="hint">· URL，多个逗号分隔</span></label><input type="text" name="chapter_lesson_materials[' + chapterIdx + '][]" placeholder="https://...pdf,https://...ppt"></div>' +
      '<div class="field-row">' +
        '<div class="field"><label>课时类型</label><select name="chapter_lesson_type[' + chapterIdx + '][]" onchange="toggleQuizQ(this)"><option value="video">视频</option><option value="text">图文</option><option value="quiz">测验</option><option value="download">资料下载</option></select></div>' +
        '<div class="field"><label>试看</label><select name="chapter_lesson_free[' + chapterIdx + '][]"><option value="0">否</option><option value="1">是</option></select></div>' +
      '</div>' +
      '<div class="field quiz-q-field" style="display:none">' +
        '<label>测验题目 <span class="hint">· JSON 格式，示例：</span><button type="button" class="btn btn-ghost btn-sm" style="padding:0 8px;font-size:10px" onclick="fillQuizExample(this)">填入示例</button></label>' +
        '<textarea name="chapter_lesson_questions[' + chapterIdx + '][]" rows="3" placeholder=\'[{"type":"single","q":"问题？","options":["A.","B."],"answer":"A"}]\'></textarea>' +
        '<div class="hint" style="font-size:11px">type: single单选/multi多选/judge判断 · answer: 单选填选项字母(A)，多选填"A,C"，判断填"对/错" · 通过线 80%</div>' +
      '</div>' +
    '</div>';
  body.appendChild(row);
}

function toggleQuizQ(sel) {
  var detail = sel.closest('.lesson-detail');
  var qf = detail ? detail.querySelector('.quiz-q-field') : null;
  if (qf) qf.style.display = (sel.value === 'quiz') ? '' : 'none';
}
function fillQuizExample(btn) {
  var ta = btn.closest('.quiz-q-field').querySelector('textarea');
  if (ta) ta.value = JSON.stringify([
    {type:'single', q:'什么是增长系统？', options:['A. 单点优化','B. 覆盖获客-留存-变现全链路的系统化打法','C. 只做内容营销'], answer:'B'},
    {type:'multi', q:'以下哪些属于增长系统模块？', options:['A. CDP 用户画像','B. CRM 客户运营','C. 数据分析报表'], answer:'A,B,C'},
    {type:'judge', q:'RFM 模型适合做客户分层运营。', answer:'对'}
  ], null, 2);
}

function toggleLessonDetail(btn) {  var detail = btn.closest('.lesson-row').querySelector('.lesson-detail');
  if (detail) detail.classList.toggle('show');
}

function addFlatLesson() {
  // Create an auto-chapter if none exists
  var container = document.getElementById('chaptersContainer');
  var existing = container.querySelector('.chapter-box');
  if (!existing) addChapter();
  // Add lesson to the first chapter
  var firstChapter = container.querySelector('.chapter-box');
  if (firstChapter) {
    var idx = firstChapter.dataset.index || '0';
    addLessonToChapter(parseInt(idx));
  }
}

function addBundle(id) {
  var input = document.getElementById('bundleInput');
  var existing = input.value.split(',').map(function(s){return s.trim()}).filter(Boolean);
  if (existing.indexOf(id) === -1) existing.push(id);
  input.value = existing.join(', ');
}

// Ensure we don't submit flat_lessons flag when using chapters
document.getElementById('courseForm').addEventListener('submit', function() {
  // Clean empty rows before submit
});

// ─── Auto Save Course ───
var courseAutoSaveTimer = null;
var courseIsDirty = false;
document.addEventListener('input', function(e) {
  if (e.target.matches('#courseForm input, #courseForm textarea, #courseForm select')) {
    courseIsDirty = true;
  }
});
courseAutoSaveTimer = setInterval(function() {
  if (!courseIsDirty) return;
  var form = document.getElementById('courseForm');
  var fd = new FormData(form);
  fd.append('auto_save', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'course-edit.php?id=' + <?=json_encode($course['id'] ?: '')?>, true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      courseIsDirty = false;
      var el = document.getElementById('autoSaveStatus');
      if (!el) { el = document.createElement('div'); el.id = 'autoSaveStatus'; el.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:8px 16px;border-radius:8px;background:var(--surface);border:1px solid var(--border);font-size:12px;color:var(--muted);z-index:9999;transition:opacity .3s'; document.body.appendChild(el); }
      el.textContent = '✓ 已自动保存 ' + new Date().toLocaleTimeString();
      el.style.opacity = '1';
      setTimeout(function() { el.style.opacity = '0'; }, 3000);
    }
  };
  xhr.send(fd);
}, 30000);
window.addEventListener('beforeunload', function(e) {
  if (courseIsDirty) { e.returnValue = '有未保存的更改，确定离开？'; }
});

// ─── 拖拽排序 ───
var dragEl = null;
document.getElementById('chaptersContainer').addEventListener('mousedown', function(e) {
  var row = e.target.closest('.lesson-row');
  var box = e.target.closest('.chapter-box');
  if (e.target.closest('input,button,select,a,textarea')) return;
  if (row) {
    row.draggable = true;
    dragEl = row;
    row.addEventListener('dragstart', function() { dragEl = row; }, { once: true });
    row.addEventListener('dragend', function() { row.draggable = false; dragEl = null; renumber(); }, { once: true });
  } else if (box) {
    box.draggable = true;
    dragEl = box;
    box.addEventListener('dragstart', function() { dragEl = box; }, { once: true });
    box.addEventListener('dragend', function() { box.draggable = false; dragEl = null; reindexChapters(); }, { once: true });
  }
});
document.querySelector('.chapter-body')?.addEventListener('dragover', function(e) { e.preventDefault(); });
document.getElementById('chaptersContainer').addEventListener('dragover', function(e) {
  var box = e.target.closest('.chapter-box');
  if (dragEl && dragEl.classList.contains('lesson-row')) { e.preventDefault(); return; }
});
// 拖拽到某个章节内部时，将课时放入该章节
document.getElementById('chaptersContainer').addEventListener('dragover', function(e) {
  if (!dragEl || !dragEl.classList.contains('lesson-row')) return;
  var body = e.target.closest('.chapter-body');
  if (body) { e.preventDefault(); body.appendChild(dragEl); }
});
function renumber() {
  document.querySelectorAll('.chapter-box').forEach(function(box) {
    var body = box.querySelector('.chapter-body');
    if (!body) return;
    var nums = body.querySelectorAll('.lesson-row .num');
    nums.forEach(function(n, i) { n.textContent = i + 1; });
    var inputs = body.querySelectorAll('.lesson-row input');
    var ci = box.dataset.index;
    inputs.forEach(function(inp) {
      if (inp.name.indexOf('chapter_lesson_title[') === 0) inp.name = 'chapter_lesson_title[' + ci + '][]';
      else if (inp.name.indexOf('chapter_lesson_duration[') === 0) inp.name = 'chapter_lesson_duration[' + ci + '][]';
    });
  });
}
function reindexChapters() {
  var boxes = document.querySelectorAll('.chapter-box');
  boxes.forEach(function(box, i) {
    box.dataset.index = i;
    var titleInput = box.querySelector('input[name^="chapter_title["]');
    var idInput = box.querySelector('input[name^="chapter_id["]');
    if (titleInput) titleInput.name = 'chapter_title[' + i + ']';
    if (idInput) idInput.name = 'chapter_id[' + i + ']';
    var body = box.querySelector('.chapter-body');
    if (body) body.dataset.chapter = i;
    renumber();
  });
}
</script>
<?php admin_footer(); ?>
