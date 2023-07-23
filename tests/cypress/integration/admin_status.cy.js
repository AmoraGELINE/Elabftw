describe('Status in admin panel', () => {
  beforeEach(() => {
    cy.login()
  })

  it('Create, update and delete a status', () => {
    const newname = 'New cypress test status'
    cy.visit('/admin.php?tab=4')
    cy.get('#statusName').type(newname)
    // create
    cy.get('[data-action="create-status"]').click().wait(500)
    cy.get('ul[data-table="status"]').find('li[data-statusid]').find('input[value="' + newname + '"]').parent().parent().as('newStatus')
    cy.get('@newStatus').find('input').first().should('have.value', newname)
    cy.get('@newStatus').find('input').first().type('something')
    cy.intercept('/api/v2/teams/1/status/17').as('statusUpdated')
    cy.get('@newStatus').find('[data-action="update-status"]').click()
    cy.wait('@statusUpdated')
    cy.get('#overlay').should('be.visible').should('contain', 'Save')
    // destroy
    cy.get('@newStatus').find('[data-action="destroy-status"]').click()
    cy.wait('@statusUpdated')
    cy.get('#overlay').should('be.visible').should('contain', 'Save')
  });
});
