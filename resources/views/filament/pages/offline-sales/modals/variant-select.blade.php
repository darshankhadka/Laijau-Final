        @if($activeModal === 'variant_select' && $selectedProductForVariant)
        <div class="lj-modal-backdrop" wire:keydown.escape="closeModal">
            <div class="lj-modal-card">
                <div class="lj-modal-header">
                    <div>
                        <div class="lj-modal-title">Select Variant</div>
                        <div style="font-size: 0.75rem; color: var(--lj-text-muted);">
                            {{ $selectedProductForVariant['name'] }}
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="lj-modal-close">✕</button>
                </div>

                <div class="lj-modal-body">
                    @php
                    $vars = $selectedProductForVariant['variants'] ?? [];
                    $colors = collect($vars)->pluck('color')->filter()->unique()->values()->all();
                    $sizes = collect($vars)->pluck('size')->filter()->unique()->values()->all();
                    @endphp

                    <!-- Color Selector -->
                    @if(count($colors) > 0)
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Color / Pattern
                        </div>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            @foreach($colors as $col)
                            <button
                                type="button"
                                wire:click="selectModalVariantColor('{{ $col }}')"
                                class="lj-cat-chip {{ $modalSelectedColor === $col ? 'active' : '' }}">
                                {{ $col }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Size Selector -->
                    @if(count($sizes) > 0)
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Size
                        </div>
                        <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
                            @foreach($sizes as $sz)
                            <button
                                type="button"
                                wire:click="selectModalVariantSize('{{ $sz }}')"
                                class="lj-cat-chip {{ $modalSelectedSize === $sz ? 'active' : '' }}">
                                {{ $sz }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Quantity Stepper -->
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--lj-text-muted); margin-bottom: 0.35rem; text-transform: uppercase;">
                            Quantity
                        </div>
                        <div class="lj-stepper">
                            <button
                                type="button"
                                wire:click="$set('modalSelectedQty', {{ max(1, $modalSelectedQty - 1) }})"
                                class="lj-step-btn">
                                −
                            </button>
                            <span class="lj-step-qty">{{ $modalSelectedQty }}</span>
                            <button
                                type="button"
                                wire:click="$set('modalSelectedQty', {{ $modalSelectedQty + 1 }})"
                                class="lj-step-btn">
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div class="lj-modal-footer">
                    <button type="button" wire:click="closeModal" class="lj-btn-secondary" style="height: 40px;">
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="addModalVariantToCart"
                        class="lj-checkout-btn"
                        style="width: auto; height: 40px; padding: 0 1.5rem;">
                        + Add to Sale
                    </button>
                </div>
            </div>
        </div>
        @endif
