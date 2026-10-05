<?php
$counts=$counts ?? array_fill_keys(array_keys(\App\DistributorService::OUTCOMES),0);
$totalPages=$totalPages ?? 1;
$reviewId=$error ? (int)($_POST['id'] ?? 0) : 0;
?>
<section class="distribution-admin" aria-labelledby="distribution-admin-title">
  <header class="distribution-admin-heading">
    <div><h1 id="distribution-admin-title">Distribution applications</h1><p>Review business details, product readiness and retail requirements.</p></div>
    <span><?= number_format(array_sum($counts)) ?> submitted applications</span>
  </header>
  <nav class="distribution-status-tabs" aria-label="Application status">
    <?php foreach (\App\DistributorService::OUTCOMES as $key=>$label): ?>
      <a href="<?= e(app_url('dashboard/distributors?status='.$key)) ?>" <?= $key===$status?'aria-current="page"':'' ?>><?= e($label) ?><span><?= number_format($counts[$key]) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($error): ?><p class="distribution-error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <div class="distribution-list-heading"><h2><?= e(\App\DistributorService::OUTCOMES[$status]) ?></h2><span><?= number_format($counts[$status]) ?> applications</span></div>
  <?php if (!$rows): ?><div class="distribution-empty"><h3>No applications in this view</h3><p>Applications with this review status will appear here.</p></div><?php endif; ?>
  <?php foreach ($rows as $row):
    $answers=json_decode((string)$row['application_data'],true);
    $answers=is_array($answers)?$answers:[];
    $retry=$reviewId===(int)$row['id'];
    $reviewStatus=$retry ? (string)($_POST['status'] ?? '') : (string)$row['status'];
  ?>
  <details class="distribution-application" <?= $retry?'open':'' ?>>
    <summary>
      <span class="distribution-app-name"><strong><?= e($answers['business_name'] ?? 'Application') ?></strong><span><?= e($row['email']) ?></span></span>
      <span class="distribution-app-date">Submitted <time><?= e($row['submitted_at'] ?: 'Not submitted') ?></time></span>
      <span class="distribution-status-label"><?= e(\App\DistributorService::OUTCOMES[$row['status']] ?? $row['status']) ?></span>
    </summary>
    <div class="distribution-application-body">
      <?php $retailPayment=\App\DistributorPaymentService::latest((int)$row['id']); ?>
      <section class="distribution-answer-group"><h3>Retail placement payment</h3>
        <?php if ($retailPayment): ?><p><?= e(\App\DistributorPaymentService::plans()[$retailPayment['plan_code']]['name'] ?? $retailPayment['plan_code']) ?> · <?= e($retailPayment['currency']) ?> <?= number_format((int)$retailPayment['amount_minor']/100,2) ?> · <?= $retailPayment['paid_at']?'Paid':'Awaiting payment' ?></p><p><?= e($retailPayment['reference']) ?></p>
        <?php else: ?><p><?= !empty($answers['retail_payment_required'])?'Package payment required':'No package payment recorded' ?></p><?php endif; ?>
      </section>
      <?php foreach (\App\DistributorService::fields() as $section=>$fields): ?>
        <section class="distribution-answer-group"><h3><?= e($section) ?></h3><dl>
          <?php foreach ($fields as $key=>[$label,$type]):
            $value=$answers[$key] ?? '';
            $value=is_scalar($value)?(string)$value:'';
          ?><div><dt><?= e($label) ?></dt><dd><?= e($type==='checkbox'?($value==='1'?'Confirmed':'Not confirmed'):($value!==''?$value:'Not provided')) ?></dd></div><?php endforeach; ?>
        </dl></section>
      <?php endforeach; ?>
      <p class="distribution-dates">Application date: <?= e($answers['application_date'] ?? 'Not provided') ?><?php if ($row['reviewed_at']): ?> · Last reviewed <?= e($row['reviewed_at']) ?><?php endif; ?></p>
      <form method="post" class="distribution-review">
        <h3>Review application</h3>
        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div class="distribution-review-fields">
          <label>Outcome <span aria-hidden="true">*</span><select name="status" required><option value="">Choose outcome</option><?php foreach (\App\DistributorService::OUTCOMES as $key=>$label): if ($key==='pending') continue; ?><option value="<?= e($key) ?>" <?= $reviewStatus===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>Catalog allowance<input type="number" name="product_limit" min="1" max="1000000" step="1" value="<?= e($retry?($_POST['product_limit'] ?? ''):($row['product_limit'] ?? '')) ?>"><small>Required for Distribution Ready approval.</small></label>
          <label class="distribution-review-notes">Feedback to applicant<textarea name="review_notes" maxlength="5000" rows="4"><?= e($retry?($_POST['review_notes'] ?? ''):($row['review_notes'] ?? '')) ?></textarea></label>
        </div>
        <button type="submit" class="btn primary">Save review and notify applicant</button>
      </form>
    </div>
  </details>
  <?php endforeach; ?>
  <nav class="distribution-pagination" aria-label="Application pages">
    <?php if ($page>1): ?><a href="<?= e(app_url('dashboard/distributors?status='.$status.'&page='.($page-1))) ?>">Previous</a><?php endif; ?>
    <span>Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
    <?php if ($page<$totalPages): ?><a href="<?= e(app_url('dashboard/distributors?status='.$status.'&page='.($page+1))) ?>">Next</a><?php endif; ?>
  </nav>
</section>
