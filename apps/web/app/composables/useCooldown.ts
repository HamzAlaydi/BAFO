/**
 * A seconds countdown for rate limits and resend cooldowns (SCREENS S7: a 429 shows the
 * `Retry-After` countdown on the button; OTP resend waits 60 s).
 *
 *   const cooldown = useCooldown()
 *   cooldown.start(error.retryAfterSeconds ?? 60)
 *   <UiButton :disabled="cooldown.active.value">{{ cooldown.active.value ? t('…', { seconds: cooldown.seconds.value }) : … }}
 */
export function useCooldown() {
  const seconds = ref(0)
  let timer: ReturnType<typeof setInterval> | null = null

  function stop(): void {
    if (timer) clearInterval(timer)
    timer = null
    seconds.value = 0
  }

  function start(value: number): void {
    if (timer) clearInterval(timer)
    seconds.value = Math.max(1, Math.ceil(value))
    timer = setInterval(() => {
      seconds.value -= 1
      if (seconds.value <= 0) stop()
    }, 1000)
  }

  if (getCurrentScope()) onScopeDispose(stop)

  return { seconds: readonly(seconds), active: computed(() => seconds.value > 0), start, stop }
}
