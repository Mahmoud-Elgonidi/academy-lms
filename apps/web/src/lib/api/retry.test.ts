import { describe, expect, it, vi } from 'vitest';
import { fetchWithRetry, waitBeforeRetry } from './retry';

const answer = (status: number, headers: Record<string, string> = {}) => new Response(status === 204 ? null : '{}', { status, headers });

describe('a read that meets the rate limit', () => {
  it('waits and asks once more, then gives the page its data', async () => {
    const fetcher = vi.fn().mockResolvedValueOnce(answer(429, { 'Retry-After': '1' })).mockResolvedValueOnce(answer(200));
    const sleep = vi.fn().mockResolvedValue(undefined);

    const response = await fetchWithRetry('http://api/x', { method: 'GET' }, fetcher, sleep);

    expect(response.status).toBe(200);
    expect(fetcher).toHaveBeenCalledTimes(2);
    expect(sleep).toHaveBeenCalledWith(1000);
  });

  it('asks only once more: a second refusal is the answer', async () => {
    const fetcher = vi.fn().mockResolvedValue(answer(429));
    const response = await fetchWithRetry('http://api/x', { method: 'GET' }, fetcher, vi.fn().mockResolvedValue(undefined));

    expect(response.status).toBe(429);
    expect(fetcher).toHaveBeenCalledTimes(2);
  });

  it('never repeats a write', async () => {
    const fetcher = vi.fn().mockResolvedValue(answer(429, { 'Retry-After': '1' }));
    const sleep = vi.fn();
    const response = await fetchWithRetry('http://api/x', { method: 'POST' }, fetcher, sleep);

    expect(response.status).toBe(429);
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(sleep).not.toHaveBeenCalled();
  });

  it('does not wait out a long limit, nor retry other answers', () => {
    expect(waitBeforeRetry(answer(429, { 'Retry-After': '30' }))).toBeNull();
    expect(waitBeforeRetry(answer(429, { 'Retry-After': '2' }))).toBe(2);
    expect(waitBeforeRetry(answer(429))).toBe(1);
    expect(waitBeforeRetry(answer(500))).toBeNull();
    expect(waitBeforeRetry(answer(200))).toBeNull();
  });
});
