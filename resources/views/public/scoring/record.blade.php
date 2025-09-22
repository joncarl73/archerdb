<x-layouts.public :league="$league">
  {{-- Mount the Livewire component; controller supplies $uuid and $score --}}
  <livewire:public.scoring.record :uuid="$uuid" :score="$score" :kioskMode="$kioskMode ?? false" :kioskReturnTo="$kioskReturnTo ?? null" />
</x-layouts.public>
