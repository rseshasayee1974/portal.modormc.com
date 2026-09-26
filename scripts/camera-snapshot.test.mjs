import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { transform } from 'esbuild';

const source = await readFile(new URL('../resources/js/Utils/cameraSnapshot.ts', import.meta.url), 'utf8');
const { code } = await transform(source, { loader: 'ts', format: 'esm' });
const { snapshotRequestUrls, requestSnapshot } = await import(`data:text/javascript;base64,${Buffer.from(code).toString('base64')}`);
const local = 'http://127.0.0.1:8089/api/cameras/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/snapshot';
test('saved ONVIF API URL is called directly, without nested img_url', () => {
    assert.deepEqual(snapshotRequestUrls(local, 2), [local]);
    assert.deepEqual(snapshotRequestUrls(local.replace('/api/', '/cameras/api/')), [local.replace('/api/', '/cameras/api/')]);
});
test('raw camera path and query remain exact for registration matching', () => {
    const camera = 'http://192.168.4.10/snapshot.JPG?channel=2&quality=high';
    const urls = snapshotRequestUrls(camera);
    assert.equal(new URL(urls[0]).searchParams.get('img_url'), camera);
    assert.equal(new URL(snapshotRequestUrls(camera, 2)[0]).port, '8074');
});
test('existing proxy URL is not wrapped again', () => {
    const proxy = 'http://127.0.0.1:8089/api/camera?img_url=http%3A%2F%2F192.168.4.10%2Fsnapshot.JPG';
    assert.deepEqual(snapshotRequestUrls(proxy), [proxy]);
});
test('blob previews and POST-only endpoints are rejected', () => {
    assert.throws(() => snapshotRequestUrls('blob:http://localhost/id'));
    assert.throws(() => snapshotRequestUrls(local + 's'));
});
test('JPEG capture needs no authentication or cookies', async () => {
    const result = await requestSnapshot(local, 1, async (url, options) => {
        assert.equal(url, local);
        assert.equal(options.credentials, 'omit');
        assert.equal(options.cache, 'no-store');
        assert.equal(options.headers, undefined);
        return new Response(new Uint8Array([255, 216, 255, 224, 0, 0]));
    });
    assert.equal(result.type, 'image/jpeg');
});
test('HTML and API errors cannot be saved as a photo', async () => {
    await assert.rejects(requestSnapshot(local, 1, async () => new Response('<html>login</html>')), /JPEG/);
    await assert.rejects(requestSnapshot(local, 1, async () => new Response('{}', { status: 401 })), /401/);
});
