<x-layouts.public :league="$league">
  {{-- Mount the Livewire component; controller supplies $uuid and $score --}}
  <livewire:public.scoring.record :uuid="$uuid" :score="$score" />
</x-layouts.public>
