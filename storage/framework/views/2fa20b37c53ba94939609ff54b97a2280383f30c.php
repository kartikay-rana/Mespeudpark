

<?php $__env->startPush('libraries_top'); ?>

<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
    <section class="section">
       <div class="row">
        <div class="col-6">
            <div class="card mt-5">
                <div class="card-body">
                    <form action="<?php echo e(route('import.post')); ?>" method="post" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="">Quiz question Import</label>
                            <input type="file" name="file" id="" class="form-control">
                            <input type="hidden" name="quiz_id" value="<?php echo e($quizeId); ?>" id="" class="form-control">
                        </div>
                        <div class="mb-3">
                            <input type="submit" class="btn btn-success w-100">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </section>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts_bottom'); ?>

<?php $__env->stopPush(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/u834781590/domains/mspedupark.com/public_html/resources/views/admin/quizzes/importQuestion.blade.php ENDPATH**/ ?>