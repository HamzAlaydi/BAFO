/**
 * Fallthrough attributes of a kit form control declared with `inheritAttrs: false`: `class` and
 * `style` go to the control's root, so layout classes set by the parent (`flex-1`, `col-span-2`,
 * `sm:w-72`) size the whole field; everything else (`name`, `autofocus`, `aria-*`, `data-*`,
 * listeners) goes to the native control.
 *
 * Functions rather than computeds: `useAttrs()` is not reactive, and the template re-reads it on
 * every render the parent triggers.
 *
 *   <UiField v-bind="rootAttrs()" …><input v-bind="controlAttrs()" …></UiField>
 */
export function useFieldAttrs() {
  const attrs = useAttrs()

  function rootAttrs(): Record<string, unknown> {
    return { class: attrs.class, style: attrs.style }
  }

  function controlAttrs(): Record<string, unknown> {
    return Object.fromEntries(Object.entries(attrs).filter(([key]) => key !== 'class' && key !== 'style'))
  }

  return { rootAttrs, controlAttrs }
}
