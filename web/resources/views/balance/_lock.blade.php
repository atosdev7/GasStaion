@if (\App\Support\Admin\OpeningLock::isLocked())
    <div class="alert alert-secondary" id="opening-locked">{{ __('initialize.opening_locked_banner', ['at' => \App\Support\Admin\OpeningLock::lockedAt()]) }}</div>
@endif
