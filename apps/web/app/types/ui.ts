import type { Component } from 'vue'
import type { RouteLocationNamedI18n } from 'vue-router'

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-ghost' | 'link'
export type ControlSize = 'sm' | 'md' | 'lg'
export type Tone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger' | 'info'

export interface SelectOption<T extends string | number = string> {
  value: T
  label: string
  disabled?: boolean
}

export interface ChoiceOption<T extends string | number = string> extends SelectOption<T> {
  description?: string
  icon?: Component
}

export interface TabItem {
  key: string
  label: string
  count?: number
  icon?: Component
  disabled?: boolean
}

export interface StepItem {
  key: string
  label: string
  description?: string
}

export interface MenuAction {
  type?: 'item'
  key: string
  label: string
  icon?: Component
  to?: RouteLocationNamedI18n
  danger?: boolean
  disabled?: boolean
  /** Set (true/false) to render a single-choice item (`menuitemradio`) with a check mark. */
  checked?: boolean
}

export interface MenuSeparator {
  type: 'separator'
  key: string
}

export type MenuEntry = MenuAction | MenuSeparator

export interface TableColumn {
  key: string
  label: string
  align?: 'start' | 'center' | 'end'
  /** Tabular figures, end-aligned: prices, counts, dates. */
  numeric?: boolean
  /** Used as the card title in the stacked mobile layout. */
  primary?: boolean
  /** Left out of the stacked cards. */
  hideOnMobile?: boolean
  /** Left out of the table layout below this width (still shown in the stacked cards). */
  hideBelow?: 'lg' | 'xl' | '2xl'
  class?: string
}

export interface KeyValueItem {
  key: string
  label: string
  value?: string | number | null
  /** LTR island (numbers, codes, e-mails, URLs). */
  ltr?: boolean
}

export interface RejectedFile {
  file: File
  reason: 'type' | 'size' | 'count'
}
