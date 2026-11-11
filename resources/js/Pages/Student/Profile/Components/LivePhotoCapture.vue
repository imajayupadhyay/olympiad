<script setup>
/**
 * Camera modal for the live photo. Streams the front camera, captures one frame to a
 * JPEG File and emits it. The camera is released on close, capture or unmount.
 */
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'captured']);

const video = ref(null);
const stream = ref(null);
const shot = ref(null);        // object URL of the captured frame
const shotFile = ref(null);
const error = ref('');
const starting = ref(false);
const countdown = ref(0);
const supported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

const stop = () => {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
};

const start = async () => {
    error.value = '';
    shot.value = null;
    shotFile.value = null;
    if (!supported) {
        error.value = 'Your browser cannot open the camera here. Use the latest Chrome, Safari or Edge, or take the photo on your phone.';
        return;
    }
    starting.value = true;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false,
        });
        await nextTick();
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play().catch(() => {});
        }
    } catch (e) {
        error.value = {
            NotAllowedError: 'Camera access was blocked. Allow camera access for this site in your browser settings, then try again.',
            NotFoundError: 'No camera was found on this device.',
            NotReadableError: 'Your camera is being used by another app. Close it and try again.',
        }[e?.name] || 'The camera could not be started. Please try again.';
    } finally {
        starting.value = false;
    }
};

const capture = () => {
    const v = video.value;
    if (!v || !v.videoWidth) return;
    const canvas = document.createElement('canvas');
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    const ctx = canvas.getContext('2d');
    // Only the live preview is mirrored (CSS, like a selfie); camera frames are not,
    // so the saved photo keeps the true orientation for ID verification.
    ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
    canvas.toBlob((blob) => {
        if (!blob) return;
        shotFile.value = new File([blob], 'live-photo.jpg', { type: 'image/jpeg' });
        shot.value = URL.createObjectURL(blob);
        stop();
    }, 'image/jpeg', 0.9);
};

const captureWithCountdown = () => {
    countdown.value = 3;
    const tick = () => {
        countdown.value -= 1;
        if (countdown.value <= 0) { capture(); return; }
        setTimeout(tick, 700);
    };
    setTimeout(tick, 700);
};

const retake = () => start();

const usePhoto = () => {
    if (!shotFile.value) return;
    emit('captured', shotFile.value);
    close();
};

const close = () => {
    stop();
    if (shot.value) URL.revokeObjectURL(shot.value);
    shot.value = null;
    shotFile.value = null;
    countdown.value = 0;
    emit('close');
};

watch(() => props.open, (isOpen) => { if (isOpen) start(); else stop(); });
onBeforeUnmount(stop);
</script>

<template>
    <Teleport to="body">
        <Transition name="cam">
            <div v-if="open" class="cam-overlay" @click.self="close">
                <div class="cam-card" role="dialog" aria-modal="true" aria-labelledby="cam-title">
                    <div class="cam-head">
                        <div>
                            <h3 id="cam-title">Take your live photo</h3>
                            <p>Face the camera in good light. Remove caps and sunglasses.</p>
                        </div>
                        <button type="button" class="cam-x" aria-label="Close camera" @click="close">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <div class="cam-stage">
                        <img v-if="shot" :src="shot" alt="Captured photo" class="cam-media" />
                        <template v-else-if="!error">
                            <video ref="video" class="cam-media mirror" playsinline muted autoplay></video>
                            <div class="cam-guide" aria-hidden="true"><span></span></div>
                            <div v-if="starting" class="cam-status">Starting camera…</div>
                            <div v-if="countdown > 0" class="cam-count">{{ countdown }}</div>
                        </template>
                        <div v-else class="cam-error">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7h3l2-2h8l2 2h3v12H3V7Z"/><path d="m4 4 16 16"/></svg>
                            <p>{{ error }}</p>
                            <button v-if="supported" type="button" class="cam-btn ghost" @click="start">Try again</button>
                        </div>
                    </div>

                    <div class="cam-actions">
                        <template v-if="shot">
                            <button type="button" class="cam-btn ghost" @click="retake">Retake</button>
                            <button type="button" class="cam-btn primary" @click="usePhoto">Use this photo</button>
                        </template>
                        <template v-else-if="!error">
                            <button type="button" class="cam-btn ghost" @click="close">Cancel</button>
                            <button type="button" class="cam-btn primary" :disabled="!stream || countdown > 0" @click="captureWithCountdown">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M3 7h3l2-2h8l2 2h3v12H3V7Z"/><circle cx="12" cy="13" r="3.5"/></svg>
                                Capture
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.cam-overlay { position: fixed; inset: 0; z-index: 70; display: grid; place-items: center; padding: 1rem; background: rgba(10,16,36,.7); backdrop-filter: blur(6px); }
.cam-card { width: 100%; max-width: 560px; background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 40px 90px -30px rgba(10,16,36,.6); }
.cam-head { display: flex; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.3rem .9rem; }
.cam-head h3 { margin: 0; font-family: "Fraunces", serif; font-weight: 600; font-size: 1.2rem; color: #0A1024; }
.cam-head p { margin: .2rem 0 0; font-size: .84rem; color: #5B6373; }
.cam-x { width: 34px; height: 34px; flex-shrink: 0; border: 0; border-radius: 10px; background: #F3E9D6; color: #5B6373; cursor: pointer; display: grid; place-items: center; }
.cam-x svg { width: 15px; height: 15px; }
.cam-stage { position: relative; overflow: hidden; background: #0A1024; aspect-ratio: 4 / 3; display: grid; place-items: center; }
.cam-media { width: 100%; height: 100%; object-fit: cover; display: block; }
.mirror { transform: scaleX(-1); }
.cam-guide { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; }
.cam-guide span { width: 46%; aspect-ratio: 3 / 4; border: 2.5px dashed rgba(242,200,75,.85); border-radius: 50% / 42%; box-shadow: 0 0 0 999px rgba(10,16,36,.35); }
.cam-status { position: absolute; color: #FBF6EC; font-size: .9rem; }
.cam-count { position: absolute; font-family: "Space Grotesk", monospace; font-size: 4.5rem; font-weight: 700; color: #fff; text-shadow: 0 8px 30px rgba(0,0,0,.5); }
.cam-error { padding: 2rem 1.5rem; text-align: center; color: #FBF6EC; display: grid; justify-items: center; gap: .8rem; }
.cam-error svg { width: 42px; height: 42px; color: #F2C84B; }
.cam-error p { margin: 0; font-size: .9rem; max-width: 380px; line-height: 1.5; }
.cam-actions { display: flex; justify-content: flex-end; gap: .6rem; padding: 1rem 1.3rem 1.2rem; }
.cam-btn { display: inline-flex; align-items: center; gap: .45rem; border: 0; cursor: pointer; font-weight: 700; font-size: .9rem; padding: .7rem 1.2rem; border-radius: 12px; }
.cam-btn svg { width: 18px; height: 18px; }
.cam-btn.primary { color: #fff; background: linear-gradient(135deg, #F2854E, #EE6A2C); box-shadow: 0 12px 26px -12px rgba(238,106,44,.7); }
.cam-btn.primary:disabled { opacity: .5; cursor: not-allowed; }
.cam-btn.ghost { background: #fff; color: #131C3D; border: 1.5px solid #E7D9BE; }
.cam-enter-active, .cam-leave-active { transition: opacity .2s; }
.cam-enter-from, .cam-leave-to { opacity: 0; }
@media (max-width: 480px) { .cam-actions .cam-btn { flex: 1; justify-content: center; } }
</style>
