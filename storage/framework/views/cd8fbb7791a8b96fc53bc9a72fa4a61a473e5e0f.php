<div class="add-answer-card mt-4 <?php echo e((empty($answer) or (!empty($loop) and $loop->iteration == 1)) ? 'main-answer-row' : ''); ?>">
    <button type="button" class="btn btn-sm btn-danger rounded-circle answer-remove <?php echo e((!empty($answer) and !empty($loop) and $loop->iteration > 1) ? '' : 'd-none'); ?>">
        <i class="fa fa-times"></i>
    </button>

    <div class="row">
        <div class="col-12">
            <div class="form-group">
                <label class="input-label"><?php echo e(trans('quiz.answer_title')); ?></label>
                <input type="text" name="ajax[answers][<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>][title]" class="form-control <?php echo e(!empty($answer) ? 'js-ajax-answer-title-'.$answer->id : ''); ?>" value="<?php echo e(!empty($answer) ? $answer->title : ''); ?>"/>
            </div>
        </div>
    </div>
    
    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group mt-15">
                                                <label class="input-label"><?php echo e('Explaination'); ?></label>
                                                <textarea id="" name="ajax[answers][<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>][explaination]" class="form-control <?php $__errorArgs = ['explaination'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>  is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" placeholder=""><?php echo !empty($answer) ? $answer->explaination : old('explaination'); ?></textarea>
                                                <?php $__errorArgs = ['explaination'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                                <div class="invalid-feedback">
                                                    <?php echo e($message); ?>

                                                </div>
                                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                            </div>
                                        </div>
                                    </div>

    <div class="row mt-2 align-items-end">
        <div class="col-12 col-md-8">
            <div class="form-group">
                <label class="input-label"><?php echo e(trans('quiz.answer_image')); ?> <span class="braces">(<?php echo e(trans('public.optional')); ?>)</span></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="input-group-text admin-file-manager" data-input="file<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>" data-preview="holder">
                            <i class="fa fa-arrow-up" class="text-white"></i>
                        </button>
                    </div>
                    <input id="file<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>" type="text" name="ajax[answers][<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>][file]" value="<?php echo e(!empty($answer) ? $answer->image : ''); ?>" class="form-control lfm-input"/>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="form-group mt-2 d-flex align-items-center justify-content-between js-switch-parent">
                <label class="js-switch" for="correctAnswerSwitch<?php echo e(!empty($answer) ? $answer->id : ''); ?>"><?php echo e(trans('quiz.correct_answer')); ?></label>
                <div class="custom-control custom-switch">
                    <input id="correctAnswerSwitch<?php echo e(!empty($answer) ? $answer->id : ''); ?>" type="checkbox" name="ajax[answers][<?php echo e(!empty($answer) ? $answer->id : 'ans_tmp'); ?>][correct]" <?php if(!empty($answer) and $answer->correct): ?> checked <?php endif; ?> class="custom-control-input js-switch">
                    <label class="custom-control-label js-switch" for="correctAnswerSwitch<?php echo e(!empty($answer) ? $answer->id : ''); ?>"></label>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts_bottom'); ?> <!-- include summernote css/js -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>
    <script src="/assets/default/vendors/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="/assets/default/vendors/feather-icons/dist/feather.min.js"></script>
    <script src="/assets/default/vendors/select2/select2.min.js"></script>
    <script src="/assets/default/vendors/moment.min.js"></script>
    <script src="/assets/default/vendors/daterangepicker/daterangepicker.min.js"></script>
    <script src="/assets/default/vendors/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
    <script src="/assets/default/vendors/bootstrap-tagsinput/bootstrap-tagsinput.min.js"></script>
    <script src="/assets/vendors/summernote/summernote-bs4.min.js"></script>
    <script src="/assets/default/vendors/sortable/jquery-ui.min.js"></script>

    <script src="/assets/default/js/admin/quiz.min.js"></script>
    <script src="/assets/admin/js/webinar.min.js"></script>
<?php $__env->stopPush(); ?>
<?php /**PATH /home/u834781590/domains/mspedupark.com/public_html/resources/views/admin/quizzes/modals/multiple_answer_form.blade.php ENDPATH**/ ?>