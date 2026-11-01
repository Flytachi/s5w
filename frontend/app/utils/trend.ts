// Week over week: the last seven *full* days against the seven before. Today is still
// running — at nine in the morning any metric would show a two-thirds drop — and whole
// weeks compare the same weekdays, when weekends are half as busy with no trend at all.
import type { TrafficDay } from '~/types/panel'

export interface Trend { known: boolean; up: boolean; delta: string }

export function trendOf(series: TrafficDay[], pick: (day: TrafficDay) => number, window = 7): Trend {
  const sum = (days: TrafficDay[]) => days.reduce((total, day) => total + pick(day), 0)
  const current = series.slice(-(window + 1), -1)
  const previous = series.slice(-(window * 2 + 1), -(window + 1))
  const now = sum(current)
  const before = sum(previous)
  const known = current.length === window && previous.length === window && before > 0
  const percent = known ? ((now - before) / before) * 100 : 0
  return { known, up: now >= before, delta: (percent >= 0 ? '+' : '−') + Math.round(Math.abs(percent)) }
}
