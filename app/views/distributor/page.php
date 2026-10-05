<?php
$brand=app_branding();
$fields=\App\DistributorService::fields();
$canEdit=!$application || in_array($application['status'],['draft','support_required','not_ready'],true);
$data += ['contact_name'=>$user['display_name'] ?? '', 'email'=>$user['email'] ?? '', 'phone'=>$user['phone'] ?? ''];
$initialStep=0;
foreach (array_values($fields) as $n=>$group) if (array_intersect_key($errors,$group)) { $initialStep=$n; break; }
?>
<link rel="stylesheet" href="<?= e(asset('css/distributor.css?v=2')) ?>">
<div class="distribution">
<?php require __DIR__.'/header.php'; ?>
<main class="distribution-layout">
<aside><p class="eyebrow">SELLER AFRICA</p><h1>Apply for Distribution</h1><p>Bring your brand to new markets.</p><img class="distribution-image" src="<?= e(asset('images/High_produce_quality.jpeg')) ?>" alt="Produce being prepared for transport"><ol><?php foreach (array_keys($fields) as $label): ?><li><?= e($label) ?></li><?php endforeach; ?></ol><p>Your information is reviewed by the Seller Africa team. Application does not guarantee retail placement.</p></aside>
<article>
<?php if (!$user): ?>
<?php require __DIR__.'/account.php'; ?>
<?php elseif (!$canEdit): ?>
<p><a class="button" href="<?= e(app_url('distributor?payment=1')) ?>">Retail placement packages &amp; payment</a></p>
<p class="eyebrow">APPLICATION STATUS</p><h2><?= e(\App\DistributorService::OUTCOMES[$application['status']] ?? $application['status']) ?></h2><p><?= e($data['business_name'] ?? '') ?></p><p>Submitted <?= e($application['submitted_at'] ?? '') ?></p><p>Our team reviews product fit, readiness, compliance and supply capacity. We will email you with the outcome.</p><?php if ($application['review_notes']): ?><p><?= nl2br(e($application['review_notes'])) ?></p><?php endif; ?><a href="<?= e(app_url('distributor')) ?>">Refresh status</a>
<?php elseif ($route === 'distributor'): ?>
<p class="eyebrow">YOUR APPLICATION</p><h2><?= e(\App\DistributorService::OUTCOMES[$application['status'] ?? ''] ?? 'Complete your application') ?></h2><?php if (!empty($application['review_notes'])): ?><div class="notice"><?= nl2br(e($application['review_notes'])) ?></div><?php endif; ?><p>Your saved details are available when you return.</p><a class="button" href="<?= e(app_url('distributor/register')) ?>">Continue application</a>
<?php else: ?>
<h2>Tell us about your business</h2><p>Required fields are marked *. Save a draft at any time.</p>
<?php if ($saved): ?><div class="notice" role="status">Your draft has been saved.</div><?php endif; ?>
<?php if ($errors): ?><div class="errors" role="alert"><strong>Please review the highlighted fields.</strong><ul><?php foreach ($errors as $key=>$error): ?><li><a href="#field-<?= e($key) ?>"><?= e($error) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
<nav class="distribution-steps" aria-label="Application steps"><?php foreach (array_keys($fields) as $n=>$label): ?><button type="button" data-step="<?= $n ?>"><?= $n+1 ?>. <?= e($label) ?></button><?php endforeach; ?></nav>
<form method="post" id="distribution-form" data-initial-step="<?= $initialStep ?>" novalidate>
<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
<?php foreach (array_values($fields) as $n=>$group): ?><fieldset data-panel="<?= $n ?>"><legend><?= e(array_keys($fields)[$n]) ?></legend>
<?php if ($n===2): ?><p>Choose "Not available yet" where applicable. Seller Africa may offer compliance and readiness support.</p><?php endif; ?>
<?php if ($n===4): ?><details class="application-review"><summary>Review your answers</summary><div id="answer-summary"></div></details><?php endif; ?>
<div class="distribution-fields">
<?php foreach ($group as $key=>[$label,$type,$required]): $value=(string)($data[$key] ?? ''); ?>
<div class="field <?= in_array($type,['textarea','checkbox'],true) ? 'wide' : '' ?>"><label for="field-<?= e($key) ?>"><?= e($label) ?><?= $required ? ' *' : ' (optional)' ?></label>
<?php $attributes=' id="field-' . e($key) . '" name="' . e($key) . '"' . ($required?' required':'') . ' aria-describedby="error-' . e($key) . '"' . (isset($errors[$key])?' aria-invalid="true"':''); ?>
<?php if (is_array($type)): ?><select<?= $attributes ?>><option value="">Select answer</option><?php foreach ($type as $option): ?><option <?= $value===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select>
<?php elseif ($type==='textarea'): ?><textarea<?= $attributes ?> maxlength="5000"><?= e($value) ?></textarea>
<?php elseif ($type==='checkbox'): ?><input<?= $attributes ?> type="checkbox" value="1" <?= $value==='1'?'checked':'' ?>>
<?php else: ?><input<?= $attributes ?> type="<?= in_array($type,['year','number'],true)?'number':($type==='country'?'text':e($type)) ?>" value="<?= e($value) ?>" maxlength="5000" <?= $type==='year'?'min="1800" max="'.date('Y').'" step="1"':($type==='number'?'min="1" max="10000000" step="1"':'') ?> <?= $key==='email'?'readonly autocomplete="email"':($type==='tel'?'autocomplete="tel" pattern="[+0-9 ()-]{7,25}"':'') ?> <?= $type==='country'?'autocomplete="country-name"':'' ?>><?php endif; ?>
<small class="field-error" id="error-<?= e($key) ?>"><?= e($errors[$key] ?? '') ?></small></div>
<?php endforeach; ?>
<?php if ($n===4): ?><div class="field"><label for="application-date">Date</label><input id="application-date" value="<?= e(date('Y-m-d')) ?>" readonly><small>Date is set automatically when submitted.</small></div><?php endif; ?>
</div></fieldset><?php endforeach; ?>
<div class="distribution-actions"><button type="button" data-back>Back</button><button type="submit" name="intent" value="draft" class="secondary">Save draft</button><button type="button" data-next>Continue</button><button type="submit" name="intent" value="submit" data-final>Submit application</button></div>
</form>
<?php endif; ?>
</article></main></div>
<script src="<?= e(asset('js/distributor.js?v=2')) ?>" defer></script>
