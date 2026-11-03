@php($startMs = $startedAt->getTimestampMs())
<span class="{{ $class ?? 'text-[10px] font-normal leading-none text-white' }}"
      wire:key="elapsed-{{ $key ?? 'item' }}-{{ $startMs }}"
      style="color: #ffe600"
      data-elapsed-since="{{ $startMs }}"
      x-data="{
          start: {{ $startMs }},
          label: '',
          timer: null,
          tick() {
              const m = Math.max(0, Math.floor((Date.now() - this.start) / 60000));
              this.label = m >= 60 ? Math.floor(m / 60) + 'h ' + (m % 60) + 'm' : m + 'min';
          },
          init() { this.tick(); this.timer = setInterval(() => this.tick(), 15000); },
          destroy() { clearInterval(this.timer); },
      }"
      x-text="label"></span>
