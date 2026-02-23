// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * @package     local_certimagegen
 * @copyright   2026 Rudraksh Batra <batra.rudraksh@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


const setHref = (id, url) => {
    const el = document.getElementById(id);
    if (el) el.setAttribute('href', url);
};

const toggleModal = (show) => {
    const modal = document.getElementById('shareModal');
    if (modal) modal.style.display = show ? 'block' : 'none';
};

const initShareButtons = () => {
    document.querySelectorAll('.openShare').forEach((btn) => {
        btn.addEventListener('click', () => {
            const urlInput = btn.closest('.bottombtn')?.querySelector('.shareURL');
            const url = urlInput?.value || '';
            const message = document.getElementById('attachmsg')?.value || 'Check this out:';
            const certificateShareURL = await Str.get_string('certificateShareURL', 'local_certimagegen');
            if (navigator.share) {
                navigator.share({
                    title: certificateShareURL,
                    url,
                }).catch(err => console.error('Share failed:', err));
                return;
            }

            const encodedUrl = encodeURIComponent(url);

            setHref('fbook', `https://www.facebook.com/sharer.php?u=${encodedUrl}`);
            setHref('linked', `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`);
            setHref('whatsapp', `https://wa.me/?text=${encodeURIComponent(message)}%20${encodedUrl}`);
            setHref('twitterx', `https://twitter.com/share?text=${encodeURIComponent(message)}&url=${encodedUrl}`);

            const shareLink = document.getElementById('shareLink');
            if (shareLink) shareLink.value = url;

            toggleModal(true);
        });
    });
};

const initModalControls = () => {
    document.querySelector('.close-btn')?.addEventListener('click', () => toggleModal(false));

    document.getElementById('copyBtn')?.addEventListener('click', async () => {
        const linkInput = document.getElementById('shareLink');
        if (linkInput) {
            try {
                await navigator.clipboard.writeText(linkInput.value);
            } catch (err) {
                console.error('Copy failed:', err);
            }
        }
    });
};

const initLockedDownloads = () => {
    document.querySelectorAll('.download-locked').forEach((link) => {
        link.addEventListener('click', () => {
            const cmid = link.dataset.cmid;
            const imgdata = document.getElementById(`imgdata${cmid}`);

            if (!imgdata) return;
            const generatingImage = await Str.get_string('generatingimage', 'local_certimagegen');
            imgdata.innerHTML = `
                <div class="certimg" style="height:240px;display:grid;justify-content:center;">
                    <div class="loader"></div>
                    <span>${generatingImage}</span>
                </div>
            `;

            // Reload after delay to refresh generated image
            setTimeout(() => window.location.reload(), 5000);
        });
    });
};

const initCertImages = () => {
    document.querySelectorAll('.cert-image').forEach(img => {
        const issuedSrc = img.dataset.issuedSrc;
        if (!issuedSrc) return;

        const certimg = img.closest('.certimg');
        if (!certimg) return;

        const loader = certimg.querySelector('.cert-loader');
        if (!loader) return;

        const tryLoadIssued = () => {
            const testImg = new Image();

            testImg.onload = () => {
                img.src = issuedSrc + '&ts=' + Date.now();
                loader.style.display = 'none';
                clearInterval(interval);
            };

            testImg.onerror = () => {
                loader.style.display = 'flex';
            };

            testImg.src = issuedSrc + '&check=' + Date.now();
        };

        // Initial attempt
        tryLoadIssued();

        // Poll every 3 seconds until loaded
        const interval = setInterval(tryLoadIssued, 3000);
    });
};

/**
 * Entry point called by Moodle
 */
export const init = () => {
    console.log('share module loaded');
    initCertImages();
    initShareButtons();
    initModalControls();
    initLockedDownloads();
};
