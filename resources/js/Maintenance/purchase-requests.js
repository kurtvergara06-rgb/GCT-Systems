window.GCTPartialNavigation.registerInitializer(
  'maintenance-purchase-requests',
  '.purchase-page',
  () => {

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


    const newPrPartsContainer =
      document.getElementById(
        'newPrPartsContainer'
      );


    const addNewPrPartBtn =
      document.getElementById(
        'addNewPrPartBtn'
      );


    openPrModal
      ?.addEventListener(
        'click',
        () => {

          openModal(
            prModal
          );

        }
      );


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


    /* =========================================================
       INITIAL NEW PR PARTS
    ========================================================= */

    if (
      newPrPartsContainer
    ) {

      const initialParts =
        newPrPartsContainer
          .dataset
          .initialParts
        || '';


      renderParts(
        newPrPartsContainer,
        initialParts,
        false
      );


      newPrPartsContainer
        .addEventListener(
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
              newPrPartsContainer,
              removeButton
            );

          }
        );

    }


    /* =========================================================
       ADD NEW PR PART
    ========================================================= */

    addNewPrPartBtn
      ?.addEventListener(
        'click',
        () => {

          if (
            !newPrPartsContainer
          ) {
            return;
          }


          const index =
            newPrPartsContainer
              .querySelectorAll(
                '.part-needed-row'
              )
              .length;


          newPrPartsContainer
            .appendChild(
              createPartRow(
                index,
                {},
                false
              )
            );


          refreshPartIndexes(
            newPrPartsContainer
          );

        }
      );


    /* =========================================================
       JO → BUS + PARTS
    ========================================================= */

    jobOrderSelect
      ?.addEventListener(
        'change',
        () => {

          const selectedOption =
            jobOrderSelect.options[
              jobOrderSelect
                .selectedIndex
            ];


          const busNo =
            selectedOption
              ?.dataset
              .bus
            || '';


          const parts =
            selectedOption
              ?.dataset
              .parts
            || '';


          if (busNoInput) {

            busNoInput.value =
              busNo;

          }


          renderParts(
            newPrPartsContainer,
            parts,
            false
          );

        }
      );


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
            'Purchase Request Details';

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
       * View Close
       */
      if (
        viewOnlyActions
      ) {

        viewOnlyActions
          .style
          .display =
          isReadonly
            ? 'flex'
            : 'none';

      }

    }


    /* =========================================================
       OPEN PR DETAILS
    ========================================================= */

    // Populate saved read-only details from the selected persisted PR row.
    // Never render dataset values as HTML.
    function populateSavedPrDetails(button) {
      const data = button.dataset;
      const setText = (id, value, fallback = '—') => {
        const element = editPrModal?.querySelector('#' + id);
        if (element) element.textContent = String(value ?? '').trim() || fallback;
      };
      setText('reviewPrNo', data.prNo);
      setText('reviewPrSummaryJobOrder', data.jobOrderNo);
      setText('reviewPrStatus', data.status);
      setText('reviewPrBusNo', data.busNo);
      setText('reviewPrCreated', data.createdAt);
      setText('reviewPrSource', data.sourceType, 'Maintenance Request');
      setText('reviewPrJobOrderNo', data.jobOrderNo);
      setText('reviewPrRequester', data.requester);
      setText('reviewPrRequestDate', data.createdAt);
      setText('reviewPrApprover', data.joApprover, 'Not recorded');
      setText('reviewPrJoAuthorization', data.joReviewStatus);
      setText('reviewPrApprovedAt', data.joApprovedAt);
      setText('reviewPrAuthorizationJobOrder', data.jobOrderNo);
      setText('reviewPrWorkToPerform', data.workToPerform);
      setText('reviewPrPurpose', data.purpose);
      setText('reviewPrTargetDate', data.targetDate);
      setText('reviewPrRemarks', data.remarks, 'No remarks provided.');
      const pill = editPrModal?.querySelector('#reviewPrStatus');
      if (pill) pill.dataset.status = (data.status || '').toLowerCase().replaceAll(' ', '-');
      const signature = editPrModal?.querySelector('#reviewPrSignature');
      const signatureFallback = editPrModal?.querySelector('#reviewPrSignatureFallback');
      if (signature) {
        const url = data.joApproved === '1' ? data.joSignatureUrl : '';
        signature.hidden = !url;
        signature.removeAttribute('src');
        if (url) signature.src = url;
        if (signatureFallback) signatureFallback.hidden = !!url;
      }
      const renderTable = (bodyId, parts, emptyText, status) => {
        const body = editPrModal?.querySelector('#' + bodyId);
        if (!body) return;
        body.replaceChildren();
        if (!parts.length) {
          const row = body.insertRow();
          const cell = row.insertCell();
          cell.colSpan = 3;
          cell.className = 'pr-review-empty';
          cell.textContent = emptyText;
          return;
        }
        parts.forEach(part => {
          const row = body.insertRow();
          const name = row.insertCell();
          const qty = row.insertCell();
          const state = row.insertCell();
          name.textContent = part.name || '—';
          qty.textContent = [part.quantity ?? 0, part.unit || ''].join(' ').trim();
          state.textContent = status;
        });
      };
      const purchased = parseParts(data.item || '');
      let reference = [];
      try {
        const parsed = JSON.parse(data.referenceIrParts || '[]');
        if (Array.isArray(parsed)) reference = parsed;
      } catch (_) {
        reference = [];
      }
      renderTable('reviewPrItemsBody', purchased, 'No requested items recorded.', data.status || 'Submitted');
      renderTable('reviewPrIrPartsBody', reference, 'No linked allocation.', data.linkedIrStatus || 'Reference only');
      setText('reviewPrItemCount', purchased.length + (purchased.length === 1 ? ' item' : ' items'));
      setText('reviewPrTotalQuantity', purchased.reduce((total, part) => total + Number(part.quantity || 0), 0));
    }

    function openPrDetails(
      button,
      mode
    ) {
      activePrNo = button.dataset.prNo || '';
      activePrMode = mode;
      populateSavedPrDetails(button);

      const status =
        button.dataset.status
        || 'Submitted';


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

    }

    let activePrNo = '';
    let activePrMode = 'view';

    function configureReviewActions(button) {
      const canEdit = button.dataset.canEdit === '1';
      const status = button.dataset.status || 'Submitted';

      return activePrMode === 'edit' &&
        canEdit &&
        !isProcessedPrStatus(status)
        ? 'edit'
        : 'view';
    }

    const syncOpenReviewPermissions = () => {
      if (!activePrNo || !editPrModal?.classList.contains('show')) {
        return;
      }

      const buttons = Array.from(document.querySelectorAll(
        '.open-view-pr-modal, .open-edit-pr-modal'
      ));
      const button = buttons.find((candidate) =>
        candidate.dataset.prNo === activePrNo &&
        (activePrMode !== 'edit' || candidate.dataset.canEdit === '1')
      ) || buttons.find((candidate) => candidate.dataset.prNo === activePrNo);

      if (!button) {
        closeModal(editPrModal);
        activePrNo = '';
        activePrMode = 'view';
        return;
      }

      openPrDetails(button, configureReviewActions(button));
    };

    window.GCTSyncOpenPrReview = syncOpenReviewPermissions;
    if (!window.GCTPrReviewRefreshBound) {
      window.addEventListener('system-regions-refreshed', async () => {
        await window.GCTSyncOpenPrReview();
      });
      window.GCTPrReviewRefreshBound = true;
    }


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
          || button.dataset.canEdit !== '1'
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


/* Keep pending PR/IR breakdowns outside scroll-clipped table containers. */
window.GCTPartialNavigation.registerInitializer('maintenance-pr-breakdown-popup', '.purchase-page', () => {
  const page = document.querySelector('.purchase-page');
  if (!page || page.dataset.breakdownPopupReady === '1') return;
  page.dataset.breakdownPopupReady = '1';
  let active = null;
  const close = () => {
    if (!active) return;
    active.details.open = false;
    active.list.classList.remove('pr-parts-floating-menu');
    active.list.removeAttribute('style');
    active.details.appendChild(active.list);
    active = null;
  };
  const position = () => {
    if (!active) return;
    const box = active.summary.getBoundingClientRect();
    const width = Math.min(360, window.innerWidth - 24);
    const left = Math.max(12, Math.min(box.left, window.innerWidth - width - 12));
    const height = Math.min(active.list.scrollHeight, 300);
    const roomBelow = window.innerHeight - box.bottom - 16;
    const roomAbove = box.top - 16;
    const up = roomBelow < Math.min(180, height) && roomAbove > roomBelow;
    Object.assign(active.list.style, {
      position: 'fixed', left: left + 'px', width: width + 'px', right: 'auto',
      top: up ? 'auto' : (box.bottom + 5) + 'px',
      bottom: up ? (window.innerHeight - box.top + 5) + 'px' : 'auto',
      maxHeight: Math.max(90, Math.min(300, up ? roomAbove : roomBelow)) + 'px',
      zIndex: '120000'
    });
  };
  page.addEventListener('click', event => {
    const summary = event.target.closest('.pr-ir-breakdown > summary');
    if (!summary) return;
    event.preventDefault();
    const details = summary.parentElement;
    if (active?.details === details) { close(); return; }
    close();
    const list = details.querySelector('.pr-pending-parts-list');
    if (!list) return;
    details.open = true;
    list.classList.add('pr-parts-floating-menu');
    document.body.appendChild(list);
    active = {details,summary,list};
    position();
  });
  document.addEventListener('click', event => {
    if (active && !active.summary.contains(event.target) && !active.list.contains(event.target)) close();
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && active) { event.preventDefault(); close(); }
  });
  window.addEventListener('resize', position);
  window.addEventListener('scroll', event => {
    if (active && !active.list.contains(event.target)) position();
  }, true);
});


/* Pending PR review uses the same approved JO endpoint as PR submission. */
window.GCTPartialNavigation.registerInitializer('maintenance-pr-pending-review-action', '.purchase-page', () => {
  const root = document.querySelector('.purchase-page');
  const modal = document.getElementById('prModal');
  const select = document.getElementById('jobOrderSelect');
  const form = document.getElementById('newPrForm');
  if (!root || !modal || !select || !form) return;
  const submit = document.getElementById('createPrBtn');
  const tbody = document.getElementById('newPrPartsBody');
  const irTbody = document.getElementById('prReviewAvailableParts');
  const text = (id, value) => { const e = document.getElementById(id); if (e) e.textContent = value || '—'; };
  const clear = (node) => { if (node) node.replaceChildren(); };
  let sequence = 0;
  const render = (node, parts, category) => {
    if (!node) return;
    node.replaceChildren();
    for (const part of parts) {
      const row = node.insertRow();
      for (const value of [part.name || '—', (part.quantity || 0) + ' ' + (part.unit || ''), category]) {
        const cell = row.insertCell();
        cell.textContent = value;
      }
    }
    if (!parts.length) {
      const row = node.insertRow();
      const cell = row.insertCell(); cell.colSpan = 3;
      cell.textContent = category === 'For Purchase' ? 'No shortage requires purchasing.' : 'No available IR allocation.';
    }
  };
  const load = async () => {
    const seq = ++sequence;
    submit.disabled = true;
    form.querySelectorAll('[data-generated-pr-part]').forEach(el => el.remove());
    const option = select.selectedOptions[0];
    const url = option?.dataset.jobOrderUrl;
    if (!url) return;
    clear(tbody); clear(irTbody);
    try {
      const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (seq !== sequence) return;
      if (!response.ok) throw new Error(data.message || 'Unable to load approved Job Order.');
      text('prReviewLinkedJo', data.job_order_no);
      text('prReviewBus', data.bus_no);
      text('prReviewMechanics', data.assigned_mechanics);
      text('prApproverName', data.approver_name);
      const bus = document.getElementById('busNoInput'); if (bus) bus.value = data.bus_no || '';
      const signature = document.getElementById('prApprovalSignature');
      const placeholder = document.getElementById('prSignaturePlaceholder');
      if (signature) { signature.hidden = !data.signature_url; if (data.signature_url) signature.src = data.signature_url; else signature.removeAttribute('src'); }
      if (placeholder) placeholder.hidden = !!data.signature_url;
      const shortages = Array.isArray(data.parts) ? data.parts : [];
      render(tbody, shortages, 'For Purchase');
      render(irTbody, Array.isArray(data.available_parts) ? data.available_parts : [], 'Available');
      shortages.forEach((part, i) => {
        for (const [field, value] of Object.entries({name:part.name, quantity:part.quantity,unit:part.unit})) {
          const input = document.createElement('input');
          input.type = 'hidden'; input.dataset.generatedPrPart = '1';
          input.name = 'parts[' + i + '][' + field + ']';
          input.value = value ?? ''; form.appendChild(input);
        }
      });
      submit.disabled = shortages.length === 0;
    } catch (error) {
      if (seq !== sequence) return;
      render(tbody, [], 'For Purchase');
      text('prReviewLinkedJo', error.message);
      submit.disabled = true;
    }
  };
  root.addEventListener('click', event => {
    const button = event.target.closest('[data-pr-review-job]');
    if (!button) return;
    event.preventDefault();
    const option = [...select.options].find(o => o.value === button.dataset.prReviewJob);
    if (!option) return;
    select.value = option.value;
    modal.classList.add('show','active');
    modal.style.display = 'flex';
    void load();
  });
  select.addEventListener('change', load);
});
