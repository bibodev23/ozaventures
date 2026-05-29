(() => {
    const WEEKLY_LIMIT_MINUTES = 35 * 60;
    const OPEN_MINUTES = 7 * 60;
    const CLOSE_MINUTES = 18 * 60;
    const STEP_MINUTES = 15;

    const modal = document.querySelector('[data-preview-modal]');
    const openButton = document.querySelector('[data-preview-open]');
    const closeButtons = document.querySelectorAll('[data-preview-close]');
    const previewBody = document.querySelector('[data-preview-body]');
    const previewSummary = document.querySelector('[data-preview-summary]');
    const scheduleForm = document.querySelector('.schedule-form');
    const schedulePeople = Array.from(document.querySelectorAll('[data-schedule-person]'));
    const openAllButton = document.querySelector('[data-schedule-open-all]');
    const closeAllButton = document.querySelector('[data-schedule-close-all]');

    if (!modal || !openButton || !previewBody || !previewSummary || !scheduleForm) {
        return;
    }

    const parseClock = (value) => {
        const match = /^(\d{2}):(\d{2})$/.exec(value);

        if (!match) {
            return null;
        }

        return Number(match[1]) * 60 + Number(match[2]);
    };

    const formatMinutes = (minutes) => {
        const hours = Math.floor(minutes / 60);
        const rest = minutes % 60;

        return `${hours}h${String(rest).padStart(2, '0')}`;
    };

    const getInputValue = (dayElement, field) => dayElement.querySelector(`[data-shift-field="${field}"]`)?.value.trim() ?? '';

    const readDay = (dayElement) => {
        const values = {
            start: getInputValue(dayElement, 'start'),
            lunch_start: getInputValue(dayElement, 'lunch_start'),
            lunch_end: getInputValue(dayElement, 'lunch_end'),
            end: getInputValue(dayElement, 'end'),
        };
        const filledCount = Object.values(values).filter(Boolean).length;

        if (filledCount === 0) {
            return {
                label: 'Repos',
                minutes: 0,
                status: 'empty',
            };
        }

        if (filledCount !== 4) {
            return {
                label: 'À compléter',
                minutes: 0,
                status: 'warning',
            };
        }

        const times = Object.fromEntries(
            Object.entries(values).map(([field, value]) => [field, parseClock(value)]),
        );

        if (Object.values(times).some((time) => time === null)) {
            return {
                label: 'Format à corriger',
                minutes: 0,
                status: 'warning',
            };
        }

        if (Object.values(times).some((time) => time < OPEN_MINUTES || time > CLOSE_MINUTES || time % STEP_MINUTES !== 0)) {
            return {
                label: 'Horaire invalide',
                minutes: 0,
                status: 'warning',
            };
        }

        if (times.lunch_start <= times.start || times.lunch_end <= times.lunch_start || times.end <= times.lunch_end) {
            return {
                label: 'Ordre à corriger',
                minutes: 0,
                status: 'warning',
            };
        }

        const minutes = (times.lunch_start - times.start) + (times.end - times.lunch_end);

        return {
            label: `${values.start}-${values.lunch_start} / ${values.lunch_end}-${values.end}`,
            minutes,
            status: 'filled',
        };
    };

    const buildPreview = () => {
        const rows = [];
        let warningCount = 0;
        let overLimitCount = 0;

        document.querySelectorAll('[data-schedule-person]').forEach((personElement) => {
            const dayResults = Array.from(personElement.querySelectorAll('[data-schedule-day]')).map(readDay);
            const totalMinutes = dayResults.reduce((total, day) => total + day.minutes, 0);
            const hasWarning = dayResults.some((day) => day.status === 'warning');
            const isOverLimit = totalMinutes > WEEKLY_LIMIT_MINUTES;

            if (hasWarning) {
                warningCount += 1;
            }

            if (isOverLimit) {
                overLimitCount += 1;
            }

            rows.push({
                name: personElement.dataset.animatorName ?? '',
                group: personElement.dataset.animatorGroup ?? '',
                dayResults,
                totalMinutes,
                hasWarning,
                isOverLimit,
            });
        });

        return {
            rows,
            warningCount,
            overLimitCount,
        };
    };

    const renderPreview = () => {
        const preview = buildPreview();

        previewBody.replaceChildren();

        preview.rows.forEach((row) => {
            const tr = document.createElement('tr');

            if (row.hasWarning) {
                tr.classList.add('has-warning');
            }

            if (row.isOverLimit) {
                tr.classList.add('is-over-limit');
            }

            const animatorCell = document.createElement('td');
            animatorCell.className = 'preview-animator';
            const animatorName = document.createElement('strong');
            animatorName.textContent = row.name;
            const animatorGroup = document.createElement('span');
            animatorGroup.textContent = row.group;
            animatorCell.append(animatorName, animatorGroup);
            tr.append(animatorCell);

            row.dayResults.forEach((day) => {
                const td = document.createElement('td');
                td.className = `preview-day preview-${day.status}`;

                const label = document.createElement('strong');
                label.textContent = day.label;
                td.append(label);

                if (day.minutes > 0) {
                    const minutes = document.createElement('span');
                    minutes.textContent = formatMinutes(day.minutes);
                    td.append(minutes);
                }

                tr.append(td);
            });

            const totalCell = document.createElement('td');
            totalCell.className = 'preview-total';
            const total = document.createElement('strong');
            total.textContent = formatMinutes(row.totalMinutes);
            totalCell.append(total);

            if (row.isOverLimit) {
                const warning = document.createElement('span');
                warning.textContent = `+${formatMinutes(row.totalMinutes - WEEKLY_LIMIT_MINUTES)} au-delà de 35h`;
                totalCell.append(warning);
            }

            tr.append(totalCell);
            previewBody.append(tr);
        });

        previewSummary.replaceChildren();

        const totalItem = document.createElement('span');
        totalItem.textContent = `${preview.rows.length} animateur(s)`;
        previewSummary.append(totalItem);

        const overLimitItem = document.createElement('span');
        overLimitItem.className = preview.overLimitCount > 0 ? 'is-danger' : 'is-ok';
        overLimitItem.textContent = preview.overLimitCount > 0
            ? `${preview.overLimitCount} dépassement(s) 35h`
            : 'Aucun dépassement 35h';
        previewSummary.append(overLimitItem);

        const warningItem = document.createElement('span');
        warningItem.className = preview.warningCount > 0 ? 'is-danger' : 'is-ok';
        warningItem.textContent = preview.warningCount > 0
            ? `${preview.warningCount} ligne(s) à compléter`
            : 'Horaires cohérents';
        previewSummary.append(warningItem);
    };

    const openPreview = () => {
        renderPreview();
        modal.hidden = false;
        document.body.classList.add('modal-open');
    };

    const closePreview = () => {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        openButton.focus();
    };

    openButton.addEventListener('click', openPreview);
    closeButtons.forEach((button) => button.addEventListener('click', closePreview));

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const accordionAnimations = new WeakMap();

    const cleanupAccordionBody = (bodyElement) => {
        bodyElement.style.height = '';
        bodyElement.style.overflow = '';
        bodyElement.style.opacity = '';
        bodyElement.style.transform = '';
    };

    const stopAccordionAnimation = (personElement) => {
        const animation = accordionAnimations.get(personElement);

        if (animation) {
            animation.cancel();
            accordionAnimations.delete(personElement);
        }
    };

    const animateAccordion = (personElement, opening) => {
        const bodyElement = personElement.querySelector('.schedule-person-body');

        if (!bodyElement || reducedMotion.matches) {
            personElement.open = opening;
            return Promise.resolve();
        }

        stopAccordionAnimation(personElement);

        if (opening) {
            personElement.open = true;
        }

        const startHeight = opening ? 0 : bodyElement.offsetHeight;
        const endHeight = opening ? bodyElement.scrollHeight : 0;

        bodyElement.style.overflow = 'hidden';
        bodyElement.style.height = `${startHeight}px`;
        bodyElement.style.opacity = opening ? '0' : '1';
        bodyElement.style.transform = opening ? 'translateY(-8px)' : 'translateY(0)';

        const animation = bodyElement.animate(
            {
                height: [`${startHeight}px`, `${endHeight}px`],
                opacity: opening ? [0, 1] : [1, 0],
                transform: opening ? ['translateY(-8px)', 'translateY(0)'] : ['translateY(0)', 'translateY(-8px)'],
            },
            {
                duration: opening ? 300 : 230,
                easing: opening ? 'cubic-bezier(.16, 1, .3, 1)' : 'cubic-bezier(.4, 0, .2, 1)',
            },
        );

        accordionAnimations.set(personElement, animation);

        return animation.finished
            .then(() => {
                if (!opening) {
                    personElement.open = false;
                }
            })
            .catch(() => undefined)
            .finally(() => {
                if (accordionAnimations.get(personElement) === animation) {
                    cleanupAccordionBody(bodyElement);
                    accordionAnimations.delete(personElement);
                }
            });
    };

    const openPerson = (personElement, closeOthers = true) => {
        if (personElement.open) {
            return;
        }

        if (closeOthers) {
            schedulePeople.forEach((otherPersonElement) => {
                if (otherPersonElement !== personElement && otherPersonElement.open) {
                    animateAccordion(otherPersonElement, false);
                }
            });
        }

        animateAccordion(personElement, true);
    };

    const closePerson = (personElement) => {
        if (!personElement.open) {
            return;
        }

        animateAccordion(personElement, false);
    };

    const setAllPeopleOpen = (open) => {
        schedulePeople.forEach((personElement) => {
            if (open) {
                openPerson(personElement, false);
            } else {
                closePerson(personElement);
            }
        });
    };

    schedulePeople.forEach((personElement) => {
        personElement.querySelector('summary')?.addEventListener('click', (event) => {
            event.preventDefault();

            if (personElement.open) {
                closePerson(personElement);
            } else {
                openPerson(personElement);
            }
        });
    });

    openAllButton?.addEventListener('click', () => setAllPeopleOpen(true));
    closeAllButton?.addEventListener('click', () => setAllPeopleOpen(false));

    scheduleForm.addEventListener('input', (event) => {
        if (!modal.hidden && event.target instanceof HTMLInputElement) {
            renderPreview();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closePreview();
        }
    });
})();
