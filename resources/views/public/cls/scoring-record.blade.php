{{-- resources/views/public/cls/scoring-record.blade.php --}}
<x-layouts.public :league="null">
  <livewire:public.cls.record
    :kind="$kind"
    :uuid="$owner->public_uuid"
    :score-id="$score->id"
    :kiosk-mode="$kioskMode ?? false"
    :kiosk-return-to="$kioskReturnTo ?? null"
  />
</x-layouts.public>
