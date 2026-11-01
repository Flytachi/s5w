/** Page numbers around the current one, the first and the last; null is a gap. */
export function pageNumbers(current: number, total: number, around = 1): (number | null)[] {
  const numbers: (number | null)[] = []
  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || Math.abs(i - current) <= around) numbers.push(i)
    else if (numbers.at(-1) !== null) numbers.push(null)
  }
  return numbers
}
