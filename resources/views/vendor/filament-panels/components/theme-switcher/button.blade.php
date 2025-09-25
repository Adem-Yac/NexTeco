@props([
    'icon',
    'theme',
])

@php
    $label = __("filament-panels::layout.actions.theme_switcher.{$theme}.label");
@endphp

<button
    aria-label="{{ $label }}"
    type="button"
    x-on:click="(theme = @js($theme)) && close()"
    x-tooltip="{
        content: @js($label),
        theme: $store.theme,
    }"
    class="fi-theme-switcher-btn flex justify-center rounded-md p-2 outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
    x-bind:class="(() => {
        if (theme === @js($theme)) {
            if (@js($theme) === 'light') {
                return 'fi-active bg-[#D9D9D9] text-primary-500';
            }
            if (@js($theme) === 'dark') {
                return 'fi-active bg-[#313030] text-primary-400';
            }
            return 'fi-active bg-gray-50 text-primary-500 dark:bg-white/5 dark:text-primary-400';
        }
        return 'text-gray-400 hover:text-gray-500 focus-visible:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:text-gray-400';
    })()"
>
    <x-filament::icon
        :alias="'panels::theme-switcher.' . $theme . '-button'"
        :icon="$icon"
        class="h-5 w-5"
    />
</button>
