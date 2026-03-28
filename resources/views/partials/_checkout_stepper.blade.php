@php
    $steps = [
        ['label' => 'Revisar viagem', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['label' => 'Dados e Pagamento', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['label' => 'Confirmação', 'icon' => 'M5 13l4 4L19 7'],
    ];
    $currentStep = $currentStep ?? 1;
@endphp

<div class="mb-8">
    <div class="flex items-center justify-between max-w-md mx-auto">
        @foreach($steps as $i => $step)
            @php $stepNum = $i + 1; @endphp
            <div class="flex flex-col items-center relative {{ $stepNum === $currentStep ? 'text-emerald-600' : ($stepNum < $currentStep ? 'text-emerald-500' : 'text-gray-400') }}">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold
                    {{ $stepNum === $currentStep ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : ($stepNum < $currentStep ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500') }}">
                    @if($stepNum < $currentStep)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        {{ $stepNum }}
                    @endif
                </div>
                <span class="text-xs font-medium mt-2 whitespace-nowrap">{{ $step['label'] }}</span>
            </div>

            @if($i < count($steps) - 1)
                <div class="flex-1 h-0.5 mx-2 -mt-5 {{ $stepNum < $currentStep ? 'bg-emerald-400' : 'bg-gray-200' }}"></div>
            @endif
        @endforeach
    </div>
</div>
