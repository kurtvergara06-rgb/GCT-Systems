window.GCTPartialNavigation.registerInitializer(
  'maintenance-purchase-requests',
  '.purchase-page',
  () => {

    /* Parts dropdown: render above the scrollable table so menus cannot be clipped. */
    const prPartsPage = document.querySelector('.purchase-page');
    let activePartsTrigger = null;
    let activePartsMenu = null;

    function closePartsMenu() {
      if (activePartsMenu) activePartsMenu.remove();
      if (activePartsTrigger) {
        activePartsTrigger.setAttribute('aria-expanded', 'false');
        activePartsTrigger.closest('details')?.removeAttribute('open');
      }
      activePartsMenu = null;
      activePartsTrigger = null;
    }

    function positionPartsMenu() {
      if (!activePartsTrigger || !activePartsMenu || !activePartsTrigger.isConnected) {
        closePartsMenu();
        return;
      }
      const rect = activePartsTrigger.getBoundingClientRect();
      const margin = 8;
      const width = Math.min(330, window.innerWidth - margin * 2);
      activePartsMenu.style.width = width + 'px';
      activePartsMenu.style.left = Math.max(margin, Math.min(rect.left, window.innerWidth - width - margin)) + 'px';
      const spaceBelow = window.innerHeight - rect.bottom;
      const spaceAbove = rect.top;
      const menuHeight = activePartsMenu.offsetHeight;
      const above = spaceBelow < Math.min(menuHeight + margin, 220) && spaceAbove > spaceBelow;
      const maxHeight = Math.max(100, (above ? spaceAbove : spaceBelow) - margin * 2);
      activePartsMenu.style.maxHeight = maxHeight + 'px';
      activePartsMenu.style.overflowY = 'auto';
      activePartsMenu.style.top = above
        ? Math.max(margin, rect.top - Math.min(menuHeight, maxHeight) - margin) + 'px'
        : Math.min(window.innerHeight - margin, rect.bottom + margin) + 'px';
    }

    prPartsPage?.addEventListener('click', event => {
      const summary = event.target.closest('.pr-parts-dropdown > summary');
      if (!summary || !prPartsPage.contains(summary)) return;
      event.preventDefault();
      event.stopPropagation();
      if (activePartsTrigger === summary) {
        closePartsMenu();
        return;
      }
      closePartsMenu();
      const source = summary.parentElement.querySelector('.pr-parts-dropdown-menu');
      if (!source) return;
      activePartsTrigger = summary;
      summary.parentElement.setAttribute('open', '');
      summary.setAttribute('aria-expanded', 'true');
      activePartsMenu = source.cloneNode(true);
      activePartsMenu.classList.add('pr-parts-floating-menu');
      Object.assign(activePartsMenu.style, {
        position: 'fixed', zIndex: '10000', bottom: 'auto', right: 'auto',
        display: 'block', visibility: 'hidden', margin: '0'
      });
      document.body.appendChild(activePartsMenu);
      positionPartsMenu();
      activePartsMenu.style.visibility = 'visible';
    });

    document.addEventListener('click', event => {
      if (activePartsMenu?.contains(event.target) || activePartsTrigger?.contains(event.target)) return;
      closePartsMenu();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closePartsMenu();
    });
    window.addEventListener('resize', closePartsMenu);
    document.addEventListener('scroll', event => {
      if (activePartsMenu && !activePartsMenu.contains(event.target)) closePartsMenu();
    }, true);

    /* =========================================================
       MODAL HELPERS
    ========================================================= */

    function openModal(modal) {

      if (!modal) {
        return;
      }


      modal.classList.add(
        'show',
        'active'
      );


      modal.style.display =
        'flex';
    }


    function closeModal(modal) {

      if (!modal) {
        return;
      }


      modal.classList.remove(
        'show',
        'active'
      );


      modal.style.display =
        '';
    }


    /* =========================================================
       ESCAPE
    ========================================================= */

    function escapeInputValue(value) {

      return String(
        value || ''
      )
        .replaceAll(
          '&',
          '&amp;'
        )
        .replaceAll(
          '"',
          '&quot;'
        )
        .replaceAll(
          '<',
          '&lt;'
        )
        .replaceAll(
          '>',
          '&gt;'
        );

    }


    /* =========================================================
       UNIT OPTIONS
    ========================================================= */

    function unitOptions(
      selectedUnit = ''
    ) {

      const units = [
        'pcs',
        'set',
        'liter',
        'gallon',
        'bottle',
        'box',
        'meter',
        'kg',
        'pack',
        'pair',
        'roll',
        'tube',
      ];


      let html =
        '<option value="">Unit</option>';


      units.forEach(
        unit => {

          html += `
            <option
              value="${unit}"
              ${
                selectedUnit === unit
                  ? 'selected'
                  : ''
              }
            >
              ${unit}
            </option>
          `;

        }
      );


      return html;
    }


    /* =========================================================
       PARSE SAVED PART TEXT
    ========================================================= */

    function parseParts(partText) {

      if (!partText) {
        return [];
      }


      return String(partText)
        .split(',')
        .map(
          part => {

            const cleanPart =
              part.trim();


            if (!cleanPart) {
              return null;
            }


            /*
             * Example:
             *
             * Oil Filter - Qty: 2 pcs
             */
            if (
              cleanPart.includes(
                ' - Qty:'
              )
            ) {

              const pieces =
                cleanPart.split(
                  ' - Qty:'
                );


              const name =
                pieces[0]
                  ?.trim()
                || '';


              const quantityWithUnit =
                pieces[1]
                  ?.trim()
                || '';


              const match =
                quantityWithUnit.match(
                  /^(\d+)\s*(.*)$/
                );


              return {

                name,

                quantity:
                  match
                    ? match[1]
                    : '',

                unit:
                  match &&
                  match[2]
                    ? match[2]
                        .trim()
                    : '',

              };

            }


            return {

              name:
                cleanPart,

              quantity:
                '',

              unit:
                '',

            };

          }
        )
        .filter(Boolean);

    }


    /* =========================================================
       RENDER REVIEW ITEMS
    ========================================================= */

    function renderReviewParts(
      partText,
      fallbackQuantity = ''
    ) {

      const body =
        document.getElementById(
          'reviewPrItemsBody'
        );


      const countLabel =
        document.getElementById(
          'reviewPrItemCount'
        );


      if (!body) {
        return;
      }


      const parts =
        parseParts(
          partText
        );


      if (
        parts.length === 1 &&
        !parts[0].quantity &&
        fallbackQuantity
      ) {

        parts[0].quantity =
          fallbackQuantity;

      }


      body.innerHTML =
        '';


      if (
        parts.length === 0
      ) {

        const row =
          document.createElement(
            'tr'
          );


        const cell =
          document.createElement(
            'td'
          );


        cell.colSpan = 3;
        cell.className =
          'pr-review-empty';
        cell.textContent =
          'No requested items recorded.';


        row.appendChild(
          cell
        );


        body.appendChild(
          row
        );

      } else {

        parts.forEach(
          (part, index) => {

            const row =
              document.createElement(
                'tr'
              );


            const numberCell =
              document.createElement(
                'td'
              );

            numberCell.textContent =
              String(
                index + 1
              );


            const nameCell =
              document.createElement(
                'td'
              );

            nameCell.textContent =
              part.name || '—';


            const quantityCell =
              document.createElement(
                'td'
              );

            quantityCell.textContent =
              part.quantity || '—';


            row.append(numberCell, nameCell, quantityCell);


            body.appendChild(
              row
            );

          }
        );

      }


      const count =
        parts.length;


      if (
        countLabel
      ) {

        countLabel.textContent =
          String(count) + ' ' +
          (
            count === 1
              ? 'item'
              : 'items'
          );

      }


    }


    /* =========================================================
       CREATE PART ROW
    ========================================================= */

    function createPartRow(
      index,
      part = {},
      isReadonly = false
    ) {

      const row =
        document.createElement(
          'div'
        );


      row.className =
        'pr-part-row part-needed-row';


      row.innerHTML = `
        <input
          type="text"
          name="parts[${index}][name]"
          placeholder="Part name"
          value="${escapeInputValue(
            part.name || ''
          )}"
          ${
            isReadonly
              ? 'disabled'
              : ''
          }
        >

        <input
          type="number"
          name="parts[${index}][quantity]"
          min="1"
          placeholder="Qty"
          value="${escapeInputValue(
            part.quantity || ''
          )}"
          ${
            isReadonly
              ? 'disabled'
              : ''
          }
        >

        <select
          name="parts[${index}][unit]"
          ${
            isReadonly
              ? 'disabled'
              : ''
          }
        >
          ${unitOptions(
            part.unit || ''
          )}
        </select>

        <button
          type="button"
          class="remove-part-btn"
          title="Remove Part"
          ${
            isReadonly
              ? 'disabled'
              : ''
          }
        >
          <i class="fa-solid fa-trash"></i>
        </button>
      `;


      return row;
    }


    /* =========================================================
       REFRESH INDEXES
    ========================================================= */

    function refreshPartIndexes(
      container
    ) {

      if (!container) {
        return;
      }


      container
        .querySelectorAll(
          '.part-needed-row'
        )
        .forEach(
          (
            row,
            index
          ) => {

            const nameInput =
              row.querySelector(
                'input[name*="[name]"]'
              );


            const quantityInput =
              row.querySelector(
                'input[name*="[quantity]"]'
              );


            const unitSelect =
              row.querySelector(
                'select[name*="[unit]"]'
              );


            if (nameInput) {

              nameInput.name =
                `parts[${index}][name]`;

            }


            if (quantityInput) {

              quantityInput.name =
                `parts[${index}][quantity]`;

            }


            if (unitSelect) {

              unitSelect.name =
                `parts[${index}][unit]`;

            }

          }
        );

    }


    /* =========================================================
       RENDER PARTS
    ========================================================= */

    function renderParts(
      container,
      partText,
      isReadonly = false
    ) {

      if (!container) {
        return;
      }


      const parts =
        parseParts(
          partText
        );


      const rows =
        parts.length
          ? parts
          : [
              {
                name: '',
                quantity: '',
                unit: '',
              },
            ];


      container.innerHTML =
        '';


      rows.forEach(
        (
          part,
          index
        ) => {

          container.appendChild(
            createPartRow(
              index,
              part,
              isReadonly
            )
          );

        }
      );


      refreshPartIndexes(
        container
      );

    }


    /* =========================================================
       PART READONLY
    ========================================================= */

    function setPartsReadonly(
      container,
      isReadonly
    ) {

      if (!container) {
        return;
      }


      container
        .querySelectorAll(
          'input, select, button'
        )
        .forEach(
          element => {

            element.disabled =
              isReadonly;

          }
        );

    }


    /* =========================================================
       REMOVE PART
    ========================================================= */

    function removePartRow(
      container,
      button
    ) {

      if (
        !container ||
        !button ||
        button.disabled
      ) {
        return;
      }


      const row =
        button.closest(
          '.part-needed-row'
        );


      if (!row) {
        return;
      }


      const rows =
        container
          .querySelectorAll(
            '.part-needed-row'
          );


      /*
       * Keep at least one row.
       */
      if (
        rows.length === 1
      ) {

        row
          .querySelectorAll(
            'input'
          )
          .forEach(
            input => {

              input.value =
                '';

            }
          );


        row
          .querySelectorAll(
            'select'
          )
          .forEach(
            select => {

              select.value =
                '';

            }
          );


        return;
      }


      row.remove();


      refreshPartIndexes(
        container
      );

    }


    /* =========================================================
       LOCKED PR STATUSES
    ========================================================= */

    function isProcessedPrStatus(
      status
    ) {

      return [
        'Approved',
        'For Purchase',
        'Ordered',
        'For Pick-up',
        'Picked Up',
        'For Delivery',
        'Delivered',
        'Issued',
      ].includes(
        status
      );

    }


    /* =========================================================
       VALIDATION MODAL
    ========================================================= */

    const validationErrorModal =
      document.getElementById(
        'validationErrorModal'
      );


    const closeValidationErrorModal =
      document.getElementById(
        'closeValidationErrorModal'
      );


    closeValidationErrorModal
      ?.addEventListener(
        'click',
        () => {

          closeModal(
            validationErrorModal
          );

        }
      );


    /* =========================================================
       NEW PR
    ========================================================= */

    const prModal =
      document.getElementById(
        'prModal'
      );


    const openPrModal =
      document.getElementById(
        'openPrModal'
      );


    const closePrModal =
      document.getElementById(
        'closePrModal'
      );


    const cancelPrModal =
      document.getElementById(
        'cancelPrModal'
      );


    const jobOrderSelect =
      document.getElementById(
        'jobOrderSelect'
      );

    const busNoInput =
      document.getElementById(
        'busNoInput'
      );

    const newPrPartsBody =
      document.getElementById(
        'newPrPartsBody'
      );


    const prPurposeBus =
      document.getElementById(
        'prPurposeBus'
      );

    const prPurpose =
      document.getElementById(
        'prPurpose'
      );

    const prApproverName =
      document.getElementById(
        'prApproverName'
      );

    const prApprovalSignature =
      document.getElementById(
        'prApprovalSignature'
      );
    const prSignaturePlaceholder = document.getElementById('prSignaturePlaceholder');
    const prJoApprovalBadge = document.getElementById('prJoApprovalBadge');

    const prPartsError =
      document.getElementById(
        'prPartsError'
      );

    const createPrBtn =
      document.getElementById(
        'createPrBtn'
      );

    let jobOrderRequestSequence = 0;

    const showPrReviewModal = () => {
      if (!prModal) return;
      const modalPanel = prModal.querySelector('.ui-form-modal');
      const modalScroller = prModal.querySelector('.ui-form-modal-scrollable');
      prModal.style.setProperty('backdrop-filter', 'blur(7px)');
      prModal.style.setProperty('-webkit-backdrop-filter', 'blur(7px)');
      openModal(prModal);
      const resetScroll = () => {
        if (modalPanel) modalPanel.scrollTop = 0;
        if (modalScroller) modalScroller.scrollTop = 0;
      };
      resetScroll();
      window.requestAnimationFrame(resetScroll);
    };

    openPrModal?.addEventListener('click', showPrReviewModal);

    // The Review action opens the existing validated PR form directly.
    // It must not depend on the removed New PR toolbar button.
    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-pr-review-job]');
      if (!trigger) return;
      showPrReviewModal();
      if (jobOrderSelect) {
        jobOrderSelect.value = trigger.dataset.prReviewJob || '';
        jobOrderSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    closePrModal
      ?.addEventListener(
        'click',
        () => {

          closeModal(
            prModal
          );

        }
      );


    cancelPrModal
      ?.addEventListener(
        'click',
        () => {

          closeModal(
            prModal
          );

        }
      );


    function renderMissingParts(parts) {
      if (!newPrPartsBody) return;

      newPrPartsBody.replaceChildren();
      if (!Array.isArray(parts) || parts.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.className = 'pr-table-message';
        cell.textContent = 'No missing parts. All requested items are available from Warehouse.';
        row.appendChild(cell);
        newPrPartsBody.appendChild(row);
        return;
      }

      parts.forEach((part, index) => {
        const row = document.createElement('tr');
        const values = [
          String(index + 1),
          part.name || '—',
          part.category || 'Uncategorized',
          `${part.quantity} ${part.unit || ''}`.trim(),
        ];

        values.forEach((value, cellIndex) => {
          const cell = document.createElement('td');
          cell.dataset.label = ['No.', 'Item Description', 'Category', 'Order Qty'][cellIndex];
          cell.textContent = value;
          row.appendChild(cell);
        });

        const availabilityCell = document.createElement('td');
        availabilityCell.dataset.label = 'Availability';
        const badge = document.createElement('span');
        badge.className = 'pr-availability-badge';
        badge.textContent = 'Unavailable';
        availabilityCell.appendChild(badge);
        row.appendChild(availabilityCell);
        newPrPartsBody.appendChild(row);
      });
    }

    async function loadSelectedJobOrder() {
      const sequence = ++jobOrderRequestSequence;
      const selectedOption = jobOrderSelect?.options[jobOrderSelect.selectedIndex];
      const dataUrl = selectedOption?.dataset.jobOrderUrl;

      if (createPrBtn) createPrBtn.disabled = true;
      if (prPartsError) {
        prPartsError.hidden = true;
        prPartsError.textContent = '';
      }
      if (prApprovalSignature) {
        prApprovalSignature.hidden = true;
        prApprovalSignature.removeAttribute('src');
      }
      if (prSignaturePlaceholder) prSignaturePlaceholder.hidden = false;
      if (prJoApprovalBadge) prJoApprovalBadge.hidden = true;
      if (prApproverName) prApproverName.textContent = 'Select a Job Order to load its approver';
      if (busNoInput) busNoInput.value = '';

      if (!dataUrl) {
        renderMissingParts([]);
        if (newPrPartsBody) {
          const message = newPrPartsBody.querySelector('.pr-table-message');
          if (message) message.textContent = 'Select an approved Job Order to check its unavailable parts.';
        }
        if (prPurposeBus) prPurposeBus.value = '';
        if (prPurpose && !prPurpose.dataset.userEdited) prPurpose.value = '';
        return;
      }

      renderMissingParts([]);
      if (newPrPartsBody) {
        const message = newPrPartsBody.querySelector('.pr-table-message');
        if (message) message.textContent = 'Checking Warehouse availability…';
      }

      try {
        const response = await fetch(dataUrl, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });
        const data = await response.json();

        if (sequence !== jobOrderRequestSequence) return;
        if (!response.ok) {
          throw new Error(data.message || 'Unable to load this Job Order.');
        }

        renderMissingParts(data.parts);
        if (prPurposeBus) {
          prPurposeBus.value = `Bus ${data.bus_no} — ${data.work_to_perform || data.job_order_no}`;
        }
        if (busNoInput) busNoInput.value = data.bus_no || '';
        if (prPurpose && !prPurpose.dataset.userEdited) {
          prPurpose.value = data.work_to_perform || '';
        }
        if (prApproverName) {
          prApproverName.textContent = data.approver_name || 'Approver not recorded';
        }
        if (prJoApprovalBadge) prJoApprovalBadge.hidden = false;
        if (prApprovalSignature && data.signature_url) {
          prApprovalSignature.src = data.signature_url;
          prApprovalSignature.hidden = false;
          if (prSignaturePlaceholder) prSignaturePlaceholder.hidden = true;
        }

        const hasMissingParts = Array.isArray(data.parts) && data.parts.length > 0;
        if (createPrBtn) createPrBtn.disabled = !hasMissingParts;
        if (!hasMissingParts && prPartsError) {
          prPartsError.textContent = 'A Purchase Requisition cannot be created because all requested parts are available.';
          prPartsError.hidden = false;
        }
      } catch (error) {
        if (sequence !== jobOrderRequestSequence) return;
        renderMissingParts([]);
        const message = newPrPartsBody?.querySelector('.pr-table-message');
        if (message) message.textContent = 'Availability could not be checked.';
        if (prPartsError) {
          prPartsError.textContent = error.message || 'Unable to check Warehouse availability. Try again.';
          prPartsError.hidden = false;
        }
      }
    }

    jobOrderSelect?.addEventListener('change', loadSelectedJobOrder);
    prApprovalSignature?.addEventListener('error', () => {
      prApprovalSignature.hidden = true;
      prApprovalSignature.removeAttribute('src');
      if (prSignaturePlaceholder) {
        prSignaturePlaceholder.textContent = 'Saved signature unavailable.';
        prSignaturePlaceholder.hidden = false;
      }
    });
    prPurpose?.addEventListener('input', () => {
      prPurpose.dataset.userEdited = 'true';
    });
    jobOrderSelect?.value && loadSelectedJobOrder();


    /* =========================================================
       EDIT / VIEW / RESUBMIT REFERENCES
    ========================================================= */

    const editPrModal =
      document.getElementById(
        'editPrModal'
      );


    const editPrForm =
      document.getElementById(
        'editPrForm'
      );


    const editPrModalTitle =
      document.getElementById(
        'editPrModalTitle'
      );


    const editPrDescription =
      document.getElementById(
        'editPrDescription'
      );


    const editPrNo =
      document.getElementById(
        'edit_pr_no'
      );


    const editJobOrderNo =
      document.getElementById(
        'edit_job_order_no'
      );


    const editBusNo =
      document.getElementById(
        'edit_bus_no'
      );


    const editStatusDisplay =
      document.getElementById(
        'edit_status_display'
      );


    const editRemarks =
      document.getElementById(
        'edit_remarks'
      );


    const editPrPartsContainer =
      document.getElementById(
        'editPrPartsContainer'
      );


    const addEditPrPartBtn =
      document.getElementById(
        'addEditPrPartBtn'
      );


    const editPrMainActions =
      document.getElementById(
        'editPrMainActions'
      );


    const viewOnlyActions =
      document.getElementById(
        'viewOnlyActions'
      );


    const submitEditPrBtn =
      document.getElementById(
        'submitEditPrBtn'
      );


    const submitEditPrText =
      document.getElementById(
        'submitEditPrText'
      );


    const submitEditPrIcon =
      document.getElementById(
        'submitEditPrIcon'
      );


    const closeEditPrModal =
      document.getElementById(
        'closeEditPrModal'
      );


    const cancelEditPrModal =
      document.getElementById(
        'cancelEditPrModal'
      );


    const closeViewOnlyPr =
      document.getElementById(
        'closeViewOnlyPr'
      );


    const reviewPrNo =
      document.getElementById(
        'reviewPrNo'
      );


    const reviewPrStatus =
      document.getElementById(
        'reviewPrStatus'
      );


    const reviewPrCreated =
      document.getElementById(
        'reviewPrCreated'
      );

    const reviewPrSummaryJobOrder =
      document.getElementById(
        'reviewPrSummaryJobOrder'
      );

    const reviewPrJoAuthorization =
      document.getElementById(
        'reviewPrJoAuthorization'
      );

    const reviewPrAuthorizationJobOrder =
      document.getElementById(
        'reviewPrAuthorizationJobOrder'
      );


    const reviewPrJobOrderNo =
      document.getElementById(
        'reviewPrJobOrderNo'
      );


    const reviewPrBusNo =
      document.getElementById(
        'reviewPrBusNo'
      );


    const reviewPrRemarks =
      document.getElementById(
        'reviewPrRemarks'
      );

    const reviewPrPurpose =
      document.getElementById(
        'reviewPrPurpose'
      );

    const reviewPrTargetDate =
      document.getElementById(
        'reviewPrTargetDate'
      );

    const reviewPrRequester =
      document.getElementById(
        'reviewPrRequester'
      );


    const reviewPrRequestDate =
      document.getElementById(
        'reviewPrRequestDate'
      );


    const reviewPrStatusText =
      document.getElementById(
        'reviewPrStatusText'
      );


    const reviewPrTotalQuantity =
      document.getElementById(
        'reviewPrTotalQuantity'
      );


    const reviewPrSource =
      document.getElementById(
        'reviewPrSource'
      );


    const reviewPrWorkToPerform =
      document.getElementById(
        'reviewPrWorkToPerform'
      );


    const prReviewInformation =
      document.getElementById(
        'prReviewInformation'
      );


    const prReviewItemsSection =
      document.getElementById(
        'prReviewItemsSection'
      );

    const prRequestDetailsSection =
      document.getElementById(
        'prRequestDetailsSection'
      );

    const prAuthorizationSection =
      document.getElementById(
        'prAuthorizationSection'
      );


    const prEditableSection =
      document.getElementById(
        'prEditableSection'
      );


    const reviewDecisionBlock =
      document.getElementById(
        'reviewDecisionBlock'
      );


    const reviewDecisionRemarks =
      document.getElementById(
        'reviewDecisionRemarks'
      );


    const reviewEditPrBtn =
      document.getElementById(
        'reviewEditPrBtn'
      );


    const reviewApprovePrBtn =
      document.getElementById(
        'reviewApprovePrBtn'
      );


    const reviewRejectPrBtn =
      document.getElementById(
        'reviewRejectPrBtn'
      );


    let activePrTrigger =
      null;


    /* =========================================================
       GET METHOD SPOOF FIELD
    ========================================================= */

    function getEditMethodInput() {

      if (!editPrForm) {
        return null;
      }


      return editPrForm
        .querySelector(
          'input[name="_method"]'
        );

    }


    /* =========================================================
       NORMAL EDIT MODE
    ========================================================= */

    function configureNormalEdit(
      button
    ) {

      if (!editPrForm) {
        return;
      }


      editPrForm.action =
        button.dataset
          .updateUrl
        || '#';


      const methodInput =
        getEditMethodInput();


      if (methodInput) {

        methodInput.disabled =
          false;


        methodInput.value =
          'PUT';

      }


      editPrForm.dataset.confirmTitle =
        'Save Purchase Request Changes?';


      editPrForm.dataset.confirmMessage =
        'Are you sure you want to save these Purchase Request changes?';


      editPrForm.dataset.confirmButton =
        'Yes, Save Changes';


      editPrForm.dataset.confirmType =
        'update';


      if (submitEditPrText) {

        submitEditPrText.textContent =
          'Save Changes';

      }


      if (submitEditPrIcon) {

        submitEditPrIcon.className =
          'fa-solid fa-floppy-disk';

      }

    }


    /* =========================================================
       REJECTED → RESUBMIT MODE
    ========================================================= */

    function configureResubmit(
      button
    ) {

      if (!editPrForm) {
        return;
      }


      editPrForm.action =
        button.dataset
          .resubmitUrl
        || '#';


      /*
       * The form-modal was created with method PUT,
       * so Laravel generated:
       *
       * <input name="_method" value="PUT">
       *
       * Resubmit route is POST.
       * Disable the method override for rejected PR.
       */
      const methodInput =
        getEditMethodInput();


      if (methodInput) {

        methodInput.disabled =
          true;

      }


      editPrForm.dataset.confirmTitle =
        'Resubmit Purchase Request?';


      editPrForm.dataset.confirmMessage =
        'Are you sure you want to submit this revised Purchase Request for approval again?';


      editPrForm.dataset.confirmButton =
        'Yes, Resubmit PR';


      editPrForm.dataset.confirmType =
        'update';


      if (submitEditPrText) {

        submitEditPrText.textContent =
          'Resubmit PR';

      }


      if (submitEditPrIcon) {

        submitEditPrIcon.className =
          'fa-solid fa-rotate';

      }

    }


    /* =========================================================
       REVIEW ACTIONS
    ========================================================= */

    function configureReviewActions(
      button,
      mode,
      status
    ) {

      const isView =
        mode === 'view';


      const canEdit =
        isView &&
        button?.dataset
          .canEdit === '1';


      const canApprove =
        isView &&
        status === 'Submitted' &&
        button?.dataset
          .canApprove === '1';


      if (
        reviewEditPrBtn
      ) {

        reviewEditPrBtn.hidden =
          !canEdit;


        const label =
          reviewEditPrBtn
            .querySelector(
              'span'
            );


        if (label) {

          label.textContent =
            status === 'Rejected'
              ? 'Revise'
              : 'Edit';

        }

      }


      [
        [
          reviewApprovePrBtn,
          canApprove,
          button?.dataset
            .approveUrl
        ],
        [
          reviewRejectPrBtn,
          canApprove,
          button?.dataset
            .rejectUrl
        ],
      ].forEach(
        ([
          control,
          visible,
          actionUrl,
        ]) => {

          if (!control) {
            return;
          }


          control.hidden =
            !visible;


          if (visible) {

            control.dataset
              .actionUrl =
              actionUrl || '';


            control.dataset
              .prNo =
              button?.dataset
                .prNo
              || '';

          }

        }
      );


      if (
        reviewDecisionBlock
      ) {

        reviewDecisionBlock.hidden =
          !canApprove;

      }

    }


    /* =========================================================
       MODAL MODE
    ========================================================= */

    function setEditPrMode(
      mode,
      status
    ) {

      const isView =
        mode === 'view';


      const isRejected =
        status ===
        'Rejected';


      const isProcessed =
        isProcessedPrStatus(
          status
        );


      const isReadonly =
        isView ||
        isProcessed;


      /*
       * Title
       */
      if (
        editPrModalTitle
      ) {

        if (isView) {

          editPrModalTitle
            .textContent =
            'Purchase Requisition Details';

        } else if (
          isRejected
        ) {

          editPrModalTitle
            .textContent =
            'Revise Purchase Request';

        } else {

          editPrModalTitle
            .textContent =
            'Edit Purchase Request';

        }

      }


      /*
       * Description
       */
      if (
        editPrDescription
      ) {

        if (isView) {

          editPrDescription
            .textContent =
            'This Purchase Request is being viewed only.';

        } else if (
          isProcessed
        ) {

          editPrDescription
            .textContent =
            'This Purchase Request can no longer be edited because it is already approved or being processed.';

        } else if (
          isRejected
        ) {

          editPrDescription
            .textContent =
            'This PR was rejected. Revise the requested parts or remarks, then resubmit the same PR for approval.';

        } else {

          editPrDescription
            .textContent =
            'Review and update this submitted Purchase Request.';

        }

      }


      /*
       * Parts
       */
      setPartsReadonly(
        editPrPartsContainer,
        isReadonly
      );


      /*
       * Remarks
       */
      if (editRemarks) {

        editRemarks.disabled =
          isReadonly;

      }


      /*
       * Add Part
       */
      if (
        addEditPrPartBtn
      ) {

        addEditPrPartBtn
          .style
          .display =
          isReadonly
            ? 'none'
            : 'inline-flex';

      }


      /*
       * Save / Resubmit
       */
      if (
        editPrMainActions
      ) {

        editPrMainActions
          .style
          .display =
          isReadonly
            ? 'none'
            : 'flex';

      }


      /*
       * Review / Edit workspace
       */
      [
        prReviewInformation,
        prReviewItemsSection,
        prRequestDetailsSection,
        prAuthorizationSection,
      ].forEach(
        element => {

          if (element) {
            element.hidden =
              !isView;
          }

        }
      );


      if (
        prEditableSection
      ) {

        prEditableSection.hidden =
          isView;

      }


      /*
       * Review footer
       */
      if (
        viewOnlyActions
      ) {

        viewOnlyActions
          .style
          .display =
          isView
            ? 'flex'
            : 'none';

      }


      configureReviewActions(
        activePrTrigger,
        mode,
        status
      );

    }


    /* =========================================================
       OPEN PR DETAILS
    ========================================================= */

    function openPrDetails(
      button,
      mode
    ) {

      const status =
        button.dataset.status
        || 'Submitted';


      activePrTrigger =
        button;

      const approvedJo = button.dataset.joApproved === '1';
      const approver = document.getElementById('reviewPrApprover');
      const approvedAt = document.getElementById('reviewPrApprovedAt');
      const signature = document.getElementById('reviewPrSignature');
      const signatureFallback = document.getElementById('reviewPrSignatureFallback');
      const sourceJoStatus = button.dataset.joReviewStatus || 'Unknown';
      if (approver) approver.textContent = approvedJo
        ? (button.dataset.joApprover || 'Approver not recorded')
        : (sourceJoStatus === 'JO not found' ? 'Source JO not found' : 'Pending JO approval (' + sourceJoStatus + ')');
      if (approvedAt) approvedAt.textContent = approvedJo ? (button.dataset.joApprovedAt || 'Not recorded') : '—';
      if (signature) {
        signature.hidden = true;
        signature.removeAttribute('src');
        signature.onload = () => {
          signature.hidden = false;
          if (signatureFallback) signatureFallback.hidden = true;
        };
        signature.onerror = () => {
          signature.hidden = true;
          if (signatureFallback) {
            signatureFallback.hidden = false;
            signatureFallback.textContent = 'Signature unavailable';
          }
        };
        if (signatureFallback) {
          signatureFallback.hidden = false;
          signatureFallback.textContent = approvedJo ? 'Loading signature…' : 'No approved JO signature available';
        }
        if (approvedJo && button.dataset.joSignatureUrl) {
          signature.src = button.dataset.joSignatureUrl;
        }
      }



      if (reviewPrNo) {
        reviewPrNo.textContent =
          button.dataset.prNo || '—';
      }


      if (reviewPrStatus) {
        reviewPrStatus.textContent =
          status;
        reviewPrStatus.dataset.status =
          status.toLowerCase().replaceAll(' ', '-');
      }

      const sourceJobOrder =
        button.dataset.jobOrderNo || '—';

      if (reviewPrSummaryJobOrder) {
        reviewPrSummaryJobOrder.textContent =
          sourceJobOrder;
      }

      if (reviewPrJoAuthorization) {
        reviewPrJoAuthorization.textContent =
          approvedJo
            ? 'JO authorization: Approved'
            : `JO authorization: ${sourceJoStatus}`;
      }

      if (reviewPrAuthorizationJobOrder) {
        reviewPrAuthorizationJobOrder.textContent =
          approvedJo
            ? `${sourceJobOrder} · Approved`
            : `${sourceJobOrder} · ${sourceJoStatus}`;
      }


      if (reviewPrCreated) {
        reviewPrCreated.textContent =
          button.dataset.createdAt || '—';
      }


      if (reviewPrJobOrderNo) {
        reviewPrJobOrderNo.textContent =
          button.dataset.jobOrderNo || '—';
      }


      if (reviewPrBusNo) {
        reviewPrBusNo.textContent =
          button.dataset.busNo || '—';
      }


      if (reviewPrRemarks) {
        reviewPrRemarks.textContent =
          button.dataset.remarks?.trim()
            || 'No remarks provided.';
      }

      if (reviewPrPurpose) {
        reviewPrPurpose.textContent =
          button.dataset.purpose?.trim() || '—';
      }

      if (reviewPrTargetDate) {
        reviewPrTargetDate.textContent =
          button.dataset.targetDate || '—';
      }

      if (reviewPrRequester) {
        reviewPrRequester.textContent =
          button.dataset.requester || '—';
      }


      if (reviewPrRequestDate) {
        reviewPrRequestDate.textContent =
          button.dataset.createdAt || '—';
      }


      if (reviewPrStatusText) {
        reviewPrStatusText.textContent =
          status === 'Submitted' && approvedJo
            ? 'Submitted · JO Approved'
            : status;
      }


      if (reviewPrTotalQuantity) {
        reviewPrTotalQuantity.textContent =
          button.dataset.quantity || '—';
      }


      if (reviewPrSource) {
        reviewPrSource.textContent =
          button.dataset.sourceType
            || 'Maintenance Request';
      }


      if (reviewPrWorkToPerform) {
        reviewPrWorkToPerform.textContent =
          button.dataset.workToPerform?.trim()
            || 'No work / repair details recorded.';
      }


      if (
        reviewDecisionRemarks &&
        mode === 'view'
      ) {
        reviewDecisionRemarks.value = '';
      }


      renderReviewParts(
        button.dataset.item || '',
        button.dataset.quantity || ''
      );


      const isRejected =
        status ===
        'Rejected';


      /*
       * Fill basic fields
       */
      if (editPrNo) {

        editPrNo.value =
          button.dataset
            .prNo
          || '';

      }


      if (editJobOrderNo) {

        editJobOrderNo.value =
          button.dataset
            .jobOrderNo
          || '';

      }


      if (editBusNo) {

        editBusNo.value =
          button.dataset
            .busNo
          || '';

      }


      if (
        editStatusDisplay
      ) {

        editStatusDisplay.value =
          status;

      }


      if (editRemarks) {

        editRemarks.value =
          button.dataset
            .remarks
          || '';

      }


      /*
       * Parts
       */
      renderParts(
        editPrPartsContainer,
        button.dataset
          .item
        || '',
        mode === 'view' ||
          isProcessedPrStatus(
            status
          )
      );


      /*
       * Configure form route.
       */
      if (
        mode !== 'view'
      ) {

        if (isRejected) {

          configureResubmit(
            button
          );

        } else {

          configureNormalEdit(
            button
          );

        }

      }


      /*
       * Mode
       */
      setEditPrMode(
        mode,
        status
      );


      openModal(
        editPrModal
      );

      const modalScroller =
        editPrModal?.querySelector(
          '.ui-form-modal-scrollable'
        );

      if (modalScroller) {
        modalScroller.scrollTop = 0;
      }

    }


    const syncOpenReviewPermissions = () => {

      if (
        !activePrTrigger ||
        !editPrModal?.classList.contains('show')
      ) {
        return;
      }

      const activePrNo =
        activePrTrigger.dataset.prNo;

      if (!activePrNo) {
        return;
      }

      const freshTrigger =
        Array.from(
          document.querySelectorAll(
            '.open-view-pr-modal'
          )
        ).find(
          button =>
            button.dataset.prNo === activePrNo
        );

      if (!freshTrigger) {
        return;
      }

      activePrTrigger =
        freshTrigger;

      configureReviewActions(
        freshTrigger,
        'view',
        freshTrigger.dataset.status || 'Submitted'
      );

    };


    window.addEventListener(
      'system-regions-refreshed',
      syncOpenReviewPermissions
    );


    /* =========================================================
       VIEW BUTTON
    ========================================================= */

    document.addEventListener(
      'click',
      event => {

        const button =
          event.target.closest(
            '.open-view-pr-modal'
          );


        if (!button) {
          return;
        }


        event.preventDefault();


        openPrDetails(
          button,
          'view'
        );

      }
    );


    reviewEditPrBtn
      ?.addEventListener(
        'click',
        () => {

          if (!activePrTrigger) {
            return;
          }


          openPrDetails(
            activePrTrigger,
            'edit'
          );

        }
      );


    /* =========================================================
       EDIT / REVISE BUTTON
    ========================================================= */

    document.addEventListener(
      'click',
      event => {

        const button =
          event.target.closest(
            '.open-edit-pr-modal'
          );


        if (!button) {
          return;
        }


        event.preventDefault();


        const status =
          button.dataset.status
          || 'Submitted';


        if (
          isProcessedPrStatus(
            status
          )
        ) {

          return;

        }


        openPrDetails(
          button,
          'edit'
        );

      }
    );


    /* =========================================================
       ADD EDIT PART
    ========================================================= */

    addEditPrPartBtn
      ?.addEventListener(
        'click',
        () => {

          if (
            !editPrPartsContainer ||
            addEditPrPartBtn.disabled
          ) {
            return;
          }


          const index =
            editPrPartsContainer
              .querySelectorAll(
                '.part-needed-row'
              )
              .length;


          editPrPartsContainer
            .appendChild(
              createPartRow(
                index,
                {},
                false
              )
            );


          refreshPartIndexes(
            editPrPartsContainer
          );

        }
      );


    /* =========================================================
       REMOVE EDIT PART
    ========================================================= */

    editPrPartsContainer
      ?.addEventListener(
        'click',
        event => {

          const removeButton =
            event.target.closest(
              '.remove-part-btn'
            );


          if (!removeButton) {
            return;
          }


          removePartRow(
            editPrPartsContainer,
            removeButton
          );

        }
      );


    /* =========================================================
       CLOSE EDIT
    ========================================================= */

    closeEditPrModal
      ?.addEventListener(
        'click',
        () => {

          closeModal(
            editPrModal
          );

        }
      );


    cancelEditPrModal
      ?.addEventListener(
        'click',
        () => {

          if (
            activePrTrigger
          ) {

            openPrDetails(
              activePrTrigger,
              'view'
            );


            return;

          }


          closeModal(
            editPrModal
          );

        }
      );


    closeViewOnlyPr
      ?.addEventListener(
        'click',
        () => {

          closeModal(
            editPrModal
          );

        }
      );


    /* =========================================================
       APPROVE / REJECT
       USE GLOBAL CONFIRMATION API ONLY
    ========================================================= */

    const approvePrForm =
      document.getElementById(
        'approvePrForm'
      );


    const rejectPrForm =
      document.getElementById(
        'rejectPrForm'
      );


    const rejectPrRemarks =
      document.getElementById(
        'rejectPrRemarks'
      );


    document.addEventListener(
      'click',
      event => {

        const button =
          event.target.closest(
            '.open-pr-confirmation'
          );


        if (!button) {
          return;
        }


        event.preventDefault();
        event.stopPropagation();


        const action =
          button.dataset
            .action;


        const actionUrl =
          button.dataset
            .actionUrl;


        const prNo =
          button.dataset
            .prNo
          || 'this purchase request';


        if (
          !action ||
          !actionUrl
        ) {

          console.error(
            'Missing Purchase Request confirmation data.'
          );

          return;

        }


        if (
          typeof window
            .openSystemConfirmation
          !== 'function'
        ) {

          console.error(
            'Global confirmation system is not loaded.'
          );

          return;

        }


        /* =====================================================
           APPROVE
        ====================================================== */

        if (
          action ===
          'approve'
        ) {

          window
            .openSystemConfirmation(
              {

                title:
                  'Approve Purchase Request?',

                messageHtml:
                  `Are you sure you want to approve <strong>${escapeInputValue(
                    prNo
                  )}</strong>?`,

                button:
                  'Yes, Approve',

                type:
                  'approve',

              },

              () => {

                if (
                  !approvePrForm
                ) {

                  console.error(
                    'approvePrForm not found.'
                  );

                  return;

                }


                approvePrForm.action =
                  actionUrl;


                approvePrForm
                  .requestSubmit();

              }
            );


          return;
        }


        /* =====================================================
           REJECT
        ====================================================== */

        if (
          action ===
          'reject'
        ) {

          window
            .openSystemConfirmation(
              {

                title:
                  'Reject Purchase Request?',

                messageHtml:
                  `Are you sure you want to reject <strong>${escapeInputValue(
                    prNo
                  )}</strong>?`,

                button:
                  'Yes, Reject',

                type:
                  'reject',

              },

              () => {

                if (
                  !rejectPrForm
                ) {

                  console.error(
                    'rejectPrForm not found.'
                  );

                  return;

                }


                rejectPrForm.action =
                  actionUrl;


                if (
                  rejectPrRemarks
                ) {

                  rejectPrRemarks.value =
                    reviewDecisionRemarks
                      ?.value
                      ?.trim()
                    || 'Rejected by Maintenance';

                }


                rejectPrForm
                  .requestSubmit();

              }
            );

        }

      }
    );


    /* =========================================================
       DELETE
    ========================================================= */

    const deletePrModal =
      document.getElementById(
        'deletePrModal'
      );


    const deletePrNo =
      document.getElementById(
        'deletePrNo'
      );


    const cancelDeletePr =
      document.getElementById(
        'cancelDeletePr'
      );


    const confirmDeletePr =
      document.getElementById(
        'confirmDeletePr'
      );


    let selectedDeleteForm =
      null;


    document.addEventListener(
      'click',
      event => {

        const button =
          event.target.closest(
            '.open-delete-pr-modal'
          );


        if (!button) {
          return;
        }


        event.preventDefault();
        event.stopPropagation();


        if (
          button.disabled
        ) {
          return;
        }


        const id =
          button.dataset.id;


        selectedDeleteForm =
          document.getElementById(
            `deletePrForm-${id}`
          );


        if (
          !selectedDeleteForm
        ) {

          console.error(
            `Delete form deletePrForm-${id} was not found.`
          );

          return;

        }


        if (deletePrNo) {

          deletePrNo.textContent =
            button.dataset
              .prNo
            || 'this purchase request';

        }


        if (
          confirmDeletePr
        ) {

          confirmDeletePr.disabled =
            false;


          confirmDeletePr.innerHTML =
            'Yes, Delete';

        }


        openModal(
          deletePrModal
        );

      }
    );


    cancelDeletePr
      ?.addEventListener(
        'click',
        event => {

          event.preventDefault();
          event.stopPropagation();


          selectedDeleteForm =
            null;


          closeModal(
            deletePrModal
          );

        }
      );


    confirmDeletePr
      ?.addEventListener(
        'click',
        event => {

          event.preventDefault();
          event.stopPropagation();


          if (
            !selectedDeleteForm
          ) {

            console.error(
              'No Purchase Request delete form was selected.'
            );

            return;

          }


          confirmDeletePr.disabled =
            true;


          confirmDeletePr.innerHTML =
            `
              <i
                class="
                  fa-solid
                  fa-spinner
                  fa-spin
                "
              ></i>
              Deleting...
            `;


          selectedDeleteForm
            .requestSubmit();

        }
      );


    /* =========================================================
       LOCAL BACKDROP CLOSE

       IMPORTANT:
       Global confirmation is excluded.
    ========================================================= */

    document.addEventListener(
      'click',
      event => {

        const overlay =
          event.target.closest(
            '.ui-form-overlay, ' +
            '.modal-overlay:not(.global-confirmation-overlay), ' +
            '.delete-modal-overlay'
          );


        if (
          !overlay ||
          event.target !==
            overlay
        ) {
          return;
        }

        if (overlay.id === 'prModal') {
          return;
        }

        closeModal(
          overlay
        );

      }
    );


    /* =========================================================
       ESC CLOSE

       GLOBAL CONFIRMATION IS OWNED BY GLOBAL JS.
    ========================================================= */

    document.addEventListener(
      'keydown',
      event => {

        if (
          event.key !==
          'Escape'
        ) {
          return;
        }


        document
          .querySelectorAll(
            '.ui-form-overlay.show, ' +
            '.modal-overlay.show:not(.global-confirmation-overlay), ' +
            '.delete-modal-overlay.show'
          )
          .forEach(
            modal => {

              closeModal(
                modal
              );

            }
          );

      }
    );


    
  }
);
