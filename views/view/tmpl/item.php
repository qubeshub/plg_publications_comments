<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 HUBzero Foundation, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @copyright Copyright 2005-2015 HUBzero Foundation, LLC.
 * @license   http://opensource.org/licenses/MIT MIT
 */

// No direct access
defined('_HZEXEC_') or die();

$cls = isset($this->cls) ? $this->cls : 'odd';

$this->comment->set('option', $this->option);
$this->comment->set('item_id', $this->obj_id);
$this->comment->set('item_type', $this->obj_type);

if ($this->obj->get('created_by') == $this->comment->get('created_by'))
{
	$cls .= ' author';
}

if ($mark = $this->params->get('onCommentMark'))
{
	if ($mark instanceof Closure)
	{
		$marked = (string) $mark($this->comment);
		$cls .= ($marked ? ' ' . $marked : '');
	}
}

$rtrn = $this->url ? $this->url : Request::getVar('REQUEST_URI', 'index.php?option=' . $this->option . '&id=' . $this->obj_id . '&active=comments', 'server');

$this->comment->set('url', $rtrn);

// Get replies
$replies = $this->comment->replies()
	->whereIn('state', array(
		Plugins\Publications\Comments\Models\Comment::STATE_PUBLISHED,
		Plugins\Publications\Comments\Models\Comment::STATE_FLAGGED,
		Plugins\Publications\Comments\Models\Comment::STATE_DELETED
	))
	->whereIn('access', User::getAuthorisedViewLevels());

if ($this->sortby == 'likes') {
	$replies = $replies->order('state', 'asc')
	                   ->order('positive', 'desc');
}
	
$replies = $replies->order('created', 'desc')
					->rows();

$deleted = ($this->comment->get('state') == Plugins\Publications\Comments\Models\Comment::STATE_DELETED);
$author_modified = ($this->comment->get('modified_by') == $this->comment->get('created_by'));
?>

<li class="comment <?php echo $cls; ?>" id="c<?php echo $this->comment->get('id'); ?>">
	<p class="comment-member-photo">
		<img src="<?php echo $this->comment->creator->picture($deleted || $this->comment->get('anonymous')); ?>" alt="" />
	</p>
	<div class="comment-content">
		<?php
		if (!$deleted && $this->params->get('comments_votable', 1))
		{
			$this->view('vote')
				->set('option', $this->option)
				->set('item', $this->comment)
				->set('params', $this->params)
				->set('url', $this->url)
				->display();
		}
		?>

		<?php $action = 'created'; ?>
		<p class="comment-title">
			<?php echo (!$deleted ? '<strong>' : '<em>'); ?>
				<?php if ($deleted) {
					echo ($author_modified ? Lang::txt('PLG_PUBLICATIONS_COMMENTS_DELETED_AUTHOR') : Lang::txt('PLG_PUBLICATIONS_COMMENTS_DELETED_ADMIN'));
					$action = 'modified';
				} elseif (!$this->comment->get('anonymous')) { ?>
					<?php if (in_array($this->comment->creator->get('access'), User::getAuthorisedViewLevels())) { ?>
						<a href="<?php echo Route::url($this->comment->creator->link()); ?>"><!--
							--><?php echo $this->escape(stripslashes($this->comment->creator->get('name'))); ?><!--
						--></a>
					<?php } else { ?>
						<?php echo $this->escape(stripslashes($this->comment->creator->get('name'))); ?>
					<?php } ?>
				<?php } else { ?>
					<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_ANONYMOUS'); ?>
				<?php } ?>
			<?php echo (!$deleted ? '</strong>' : '</em>'); ?>

			<a class="permalink" href="<?php echo $this->comment->link(); ?>" title="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_PERMALINK'); ?>">
				<span class="comment-date-at"><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_AT'); ?></span>
				<span class="time"><time datetime="<?php echo call_user_func(array($this->comment, $action)); ?>"><?php echo call_user_func_array(array($this->comment, $action), array('time')); ?></time></span>
				<span class="comment-date-on"><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_ON'); ?></span>
				<span class="date"><time datetime="<?php echo call_user_func(array($this->comment, $action)); ?>"><?php echo call_user_func_array(array($this->comment, $action), array('date')); ?></time></span>
			</a>
		</p>

		<?php if (!$deleted): ?>
			<div class="comment-body">
				<?php
				if ($this->comment->isReported())
				{
					echo '<p class="warning">' . Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPORTED_AS_ABUSIVE') . '</p>';
				}
				else
				{
					echo $this->comment->content;
				}
				?>
			</div><!-- / .comment-body -->
		<?php endif; ?>

		<?php if (!$this->comment->isReported() && !$deleted) { ?>
			<div class="comment-attachments">
				<?php
				foreach ($this->comment->files()->rows() as $attachment)
				{
					if (!trim($attachment->get('description')))
					{
						$attachment->set('description', $attachment->get('filename'));
					}

					if ($attachment->isImage())
					{
						if ($attachment->width() > 400)
						{
							$html = '<p><a href="' . Route::url($attachment->link()) . '"><img src="' . Route::url($attachment->link()) . '" alt="' . $attachment->get('description') . '" width="400" /></a></p>';
						}
						else
						{
							$html = '<p><img src="' . Route::url($attachment->link()) . '" alt="' . $attachment->get('description') . '" /></p>';
						}
					}
					else
					{
						$html = '<p class="attachment"><a href="' . Route::url($attachment->link()) . '" title="' . $attachment->get('description') . '">' . $attachment->get('description') . '</a></p>';
					}

					echo $html;
				}
				?>
			</div><!-- / .comment-attachments -->
		<?php } ?>

		<?php if (!$this->comment->isReported() && !$deleted) { ?>
			<p class="comment-options">
				<?php if (($this->params->get('access-delete-comment') && $this->comment->get('created_by') == User::get('id')) || $this->params->get('access-manage-comment')) { ?>
					<a class="icon-delete delete" href="<?php echo Route::url($this->comment->link('delete')); ?>" data-txt-confirm="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_CONFIRM'); ?>"><!--
						--><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_DELETE'); ?><!--
					--></a>
				<?php } ?>
				<?php if (($this->params->get('access-edit-comment') && $this->comment->get('created_by') == User::get('id')) || $this->params->get('access-manage-comment')) { ?>
					<a class="icon-edit edit" href="<?php echo Route::url($this->comment->link('edit')); ?>"><!--
						--><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_EDIT'); ?><!--
					--></a>
				<?php } ?>
				<?php if ($this->params->get('access-create-comment') && $this->depth < $this->params->get('comments_depth', 3)) { ?>
					<a class="icon-reply reply" data-txt-active="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_CANCEL'); ?>" data-txt-inactive="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPLY'); ?>" href="#" rel="comment-form<?php echo $this->comment->get('id'); ?>"><!--
						--><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPLY'); ?><!--
					--></a>
				<?php } ?>
					<a class="icon-abuse abuse" data-txt-flagged="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPORTED_AS_ABUSIVE'); ?>" href="<?php echo Route::url($this->comment->link('report')); ?>"><!--
						--><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPORT_ABUSE'); ?><!--
					--></a>
			</p><!-- / .comment-options -->
		<?php } ?>
		<?php if ($this->params->get('access-create-comment') && $this->depth < $this->params->get('comments_depth', 3)) { ?>
			<div class="addcomment hide" id="comment-form<?php echo $this->comment->get('id'); ?>">
				<form action="<?php echo Route::url($this->comment->link('base')); ?>" method="post" enctype="multipart/form-data">
					<fieldset>
						<legend>
							<span><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_REPLYING_TO', (!$this->comment->get('anonymous') ? $this->comment->get('name') : Lang::txt('PLG_PUBLICATIONS_COMMENTS_ANONYMOUS'))); ?></span>
						</legend>

						<input type="hidden" name="comment[id]" value="0" />
						<input type="hidden" name="comment[item_id]" value="<?php echo $this->escape($this->comment->get('item_id')); ?>" />
						<input type="hidden" name="comment[item_type]" value="<?php echo $this->escape($this->comment->get('item_type')); ?>" />
						<input type="hidden" name="comment[parent]" value="<?php echo $this->comment->get('id'); ?>" />
						<input type="hidden" name="comment[created]" value="" />
						<input type="hidden" name="comment[created_by]" value="<?php echo $this->escape(User::get('id')); ?>" />
						<input type="hidden" name="comment[state]" value="1" />
						<input type="hidden" name="comment[access]" value="1" />
						<input type="hidden" name="option" value="<?php echo $this->escape($this->option); ?>" />
						<input type="hidden" name="id" value="<?php echo $this->obj->get('id'); ?>" />
						<input type="hidden" name="v" value="<?php echo $this->obj->get('version_number'); ?>" />
						<input type="hidden" name="active" value="comments" />
						<input type="hidden" name="action" value="commentsave" />
						<input type="hidden" name="no_html" value="1" />

						<?php echo Html::input('token'); ?>

						<label for="comment_<?php echo $this->comment->get('id'); ?>_content">
							<span class="label-text"><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_ENTER_COMMENTS'); ?></span>
							<?php
							echo $this->editor('comment[content]', '', 35, 4, 'comment_' . $this->comment->get('id') . '_content', array('class' => 'minimal no-footer'));
							?>
						</label>

						<label class="comment-<?php echo $this->comment->get('id'); ?>-file" for="comment-<?php echo $this->comment->get('id'); ?>-file">
							<span class="label-text"><?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_ATTACH_FILE'); ?>:</span>
							<input type="file" name="comment_file" id="comment-<?php echo $this->comment->get('id'); ?>-file" />
						</label>

						<label class="reply-anonymous-label" for="comment-<?php echo $this->comment->get('id'); ?>-anonymous">
							<input class="option" type="checkbox" name="comment[anonymous]" id="comment-<?php echo $this->comment->get('id'); ?>-anonymous" value="1" />
							<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_POST_COMMENT_ANONYMOUSLY'); ?>
						</label>

						<p class="submit">
							<input type="submit" value="<?php echo Lang::txt('PLG_PUBLICATIONS_COMMENTS_POST_COMMENT'); ?>" />
						</p>
					</fieldset>
				</form>
			</div><!-- / .addcomment -->
		<?php } ?>
	</div><!-- / .comment-content -->

	<?php
	if (($this->depth < $this->params->get('comments_depth', 3)) && $replies->count())
	{	
		$this->view('list')
			->set('option', $this->option)
			->set('comments', $replies)
			->set('obj_type', $this->obj_type)
			->set('obj_id', $this->obj_id)
			->set('obj', $this->obj)
			->set('params', $this->params)
			->set('depth', $this->depth)
			->set('sortby', $this->sortby)
			->set('url', $this->url)
			->set('cls', $cls)
			->display();
	}
	?>
</li>